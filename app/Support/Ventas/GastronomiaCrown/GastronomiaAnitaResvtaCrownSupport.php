<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaCrown;

use App\ApiAnita;
use App\Models\Caja\Cobranza;
use App\Models\Configuracion\Centrocosto;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionAnitaGrabacionSupport;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resumen de venta gastronomía Crown en Informix (`resvta`), paridad {@see a-comandera.c::graba_resvta}.
 *
 * Columnas SQL del bridge en /usr2/casino (tabla base; sin alters posteriores de host/cliente/lista).
 * El C-ISAM usa el puntero `resv_tot_habitacion`; en SQL la columna es `resv_tot_hab`.
 */
final class GastronomiaAnitaResvtaCrownSupport
{
    private const TOLERANCIA = 0.02;

    /** Paridad a-comandera: SDESCUENTO / CLIENTE(Invitados) / STAFF / DESCUENTO. */
    private const TIPO_DTO_NINGUNO = 0;

    private const TIPO_DTO_INVITADOS = 1;

    private const TIPO_DTO_STAFF = 2;

    private const TIPO_DTO_DESCUENTO = 3;

    public static function corresponde(): bool
    {
        return EntornoEmpresaSupport::esCrown()
            && (bool) config('gastronomia.sincronizar_anita_al_facturar', true)
            && FacturacionAnitaGrabacionSupport::habilitada();
    }

    /**
     * Inserta resvta tras la grabación mínima (venta/vengrav/vencae).
     *
     * @param  array<string, mixed>  $anitaPendiente
     */
    public static function grabarTrasVenta(int $ventaId, array $anitaPendiente = []): void
    {
        if (! self::corresponde() || $ventaId <= 0) {
            return;
        }

        $venta = Venta::query()
            ->with([
                'puntoventas:id,codigo,empresa_id',
                'usuario:id',
                'gastronomiaEmision.configuracionPuntoventa.sala:id,codigo',
            ])
            ->find($ventaId);
        if ($venta === null) {
            Log::warning('gastronomia.anita.resvta.venta_inexistente', ['venta_id' => $ventaId]);

            return;
        }

        $fila = self::armarFilaDesdeVenta($venta, $anitaPendiente);
        if ($fila === null) {
            return;
        }

        $salaCodigo = trim((string) ($anitaPendiente['sala_codigo'] ?? ''))
            ?: GastronomiaCrownAnitaBridgeSupport::salaCodigoDesdeVenta($venta);

        self::insertar(
            $fila,
            $anitaPendiente['path_sistema'] ?? null,
            (int) ($venta->puntoventas->codigo ?? 0),
            $salaCodigo,
        );
    }

    /**
     * @param  array<string, mixed>  $anitaPendiente
     * @return array<string, int|float|string>|null
     */
    public static function armarFilaDesdeVenta(Venta $venta, array $anitaPendiente = []): ?array
    {
        $tipo = strtoupper(substr(trim((string) ($venta->codigo ?? '')), 0, 3));
        $letra = strtoupper(substr(trim((string) ($venta->letra ?? 'B')), 0, 1)) ?: 'B';
        $sucursal = (int) ($venta->puntoventas->codigo ?? $anitaPendiente['puntoventa_codigo'] ?? 0);
        $nro = (int) ($venta->numerocomprobante ?? 0);
        if ($tipo === '' || $sucursal <= 0 || $nro <= 0) {
            Log::warning('gastronomia.anita.resvta.clave_incompleta', [
                'venta_id' => $venta->id,
                'tipo' => $tipo,
                'sucursal' => $sucursal,
                'nro' => $nro,
            ]);

            return null;
        }

        $cuenta = GastroCrownCuenta::query()
            ->with([
                'mozo:id,codigo',
                'mesa:id,numeromesa,codigo',
                'descuentoGastronomia:id,codigo',
                'clienteInternoDescuento:id,codigo',
            ])
            ->where('venta_id', (int) $venta->id)
            ->first();

        $total = round(abs((float) $venta->total), 2);
        $medios = self::totalesMediosDesdeCobranza((int) $venta->id, $cuenta, $total);

        $tipoDto = self::resolverTipoDto($cuenta);
        // Cortesía Anita: 0.01 + descuento → totales en cero (a-comandera).
        if ($total <= 0.011 && $tipoDto !== self::TIPO_DTO_NINGUNO) {
            $total = 0.0;
            $medios = self::mediosEnCero();
        }

        $fechaJornada = self::fechaEntera(
            (string) ($venta->fechajornada ?? $venta->fecha ?? Carbon::now()->toDateString())
        );
        $hora = Carbon::parse($venta->created_at ?? $venta->fecha ?? 'now')->format('H:i');

        $ccostoCodigo = 0;
        if ($cuenta && (int) ($cuenta->centrocosto_id ?? 0) > 0) {
            $ccostoCodigo = (int) (Centrocosto::query()->whereKey((int) $cuenta->centrocosto_id)->value('codigo') ?? 0);
        } elseif ((int) ($anitaPendiente['codigo_centrocosto'] ?? 0) > 0) {
            $ccostoCodigo = (int) $anitaPendiente['codigo_centrocosto'];
        }

        $mozo = 0;
        if ($cuenta?->mozo !== null) {
            $mozo = (int) preg_replace('/\D+/', '', (string) ($cuenta->mozo->codigo ?? '')) ?: 0;
        }

        $mesa = 0;
        if ($cuenta?->mesa !== null) {
            $mesa = (int) ($cuenta->mesa->numeromesa ?? 0);
            if ($mesa <= 0) {
                $mesa = (int) preg_replace('/\D+/', '', (string) ($cuenta->mesa->codigo ?? '')) ?: 0;
            }
        }

        $habitacion = (int) ($cuenta->habitacion ?? 0);
        $cubiertos = $habitacion > 0 ? 0 : (int) ($cuenta->cubiertos ?? 0);

        $usuarioId = (int) ($venta->usuario_id ?? 0);
        if ($usuarioId <= 0 && $cuenta) {
            $usuarioId = (int) ($cuenta->usuario_apertura_id ?? 0);
        }

        return [
            'resv_sucursal' => $sucursal,
            'resv_tipo_dto' => $tipoDto,
            'resv_fecha' => $fechaJornada,
            'resv_hora' => $hora,
            'resv_tipo' => $tipo,
            'resv_letra' => $letra,
            'resv_nro' => $nro,
            'resv_habitacion' => $habitacion,
            'resv_mozo' => $mozo,
            'resv_mesa' => $mesa,
            'resv_cubierto' => $cubiertos,
            'resv_total' => $total,
            'resv_tot_efectivo' => $medios['efectivo'],
            'resv_tot_tarjeta' => $medios['tarjeta'],
            'resv_tot_cheque' => $medios['cheque'],
            'resv_tot_ctacte' => $medios['ctacte'],
            'resv_tot_hab' => $medios['habitacion'],
            'resv_tot_propina' => 0.0,
            'resv_usuario' => $usuarioId,
            'resv_ccosto' => $ccostoCodigo,
            'resv_empresa' => (int) ($venta->puntoventas->empresa_id ?? 0),
            'resv_legajo' => (int) ($cuenta->autorizante_usuario_id ?? 0),
        ];
    }

    /**
     * @param  array<string, int|float|string>  $fila
     */
    public static function insertar(array $fila, ?string $pathSistema, int $sucursalCodigo, ?string $salaCodigo = null): void
    {
        $campos = array_keys($fila);
        $valores = [];
        foreach ($fila as $valor) {
            if (is_string($valor)) {
                $valores[] = "'".str_replace("'", "''", $valor)."'";
            } else {
                $valores[] = "'".$valor."'";
            }
        }

        $payload = [
            'tabla' => 'resvta',
            'acc' => 'insert',
            'campos' => implode(', ', $campos),
            'valores' => implode(', ', $valores),
        ];

        $path = is_string($pathSistema) && trim($pathSistema) !== ''
            ? trim($pathSistema)
            : PedidoFacturaAnitaArchivosSupport::pathSistemaParaSucursal($sucursalCodigo);
        if (is_string($path) && $path !== '') {
            $payload['path_sistema'] = $path;
        }
        // CIP→cncadmin; otras salas su ifx; sin sala no pisa IFX global.
        $payload = GastronomiaCrownAnitaBridgeSupport::mergePayload($payload, $salaCodigo);

        try {
            (new ApiAnita)->apiCallEscritura($payload, 'resvta insert', 'gastronomia.anita.resvta.fallo');
            Log::info('gastronomia.anita.resvta.ok', [
                'tipo' => $fila['resv_tipo'] ?? null,
                'letra' => $fila['resv_letra'] ?? null,
                'sucursal' => $fila['resv_sucursal'] ?? null,
                'nro' => $fila['resv_nro'] ?? null,
                'total' => $fila['resv_total'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::error('gastronomia.anita.resvta.fallo', [
                'msg' => $e->getMessage(),
                'fila' => $fila,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{efectivo:float,tarjeta:float,cheque:float,ctacte:float,habitacion:float}
     */
    public static function totalesMediosDesdeCobranza(int $ventaId, ?GastroCrownCuenta $cuenta, float $totalFactura): array
    {
        $medios = self::mediosEnCero();

        if ($cuenta !== null && ($cuenta->tipo_cobro ?? '') === GastroCrownCuenta::COBRO_HOTEL) {
            $medios['habitacion'] = $totalFactura;

            return $medios;
        }

        $cobranza = Cobranza::query()
            ->where('venta_id', $ventaId)
            ->with(['caja_movimientos.caja_movimiento_cuentacajas.cuentacajas:id,codigo,nombre'])
            ->orderByDesc('id')
            ->first();

        if ($cobranza === null) {
            // Sin cobranza y total > 0: a-comandera vuelca a efectivo si no hay dto.
            if ($totalFactura > self::TOLERANCIA) {
                $medios['efectivo'] = $totalFactura;
            }

            return $medios;
        }

        foreach ($cobranza->caja_movimientos as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas as $linea) {
                $monto = round(abs((float) ($linea->monto ?? 0)), 2);
                if ($monto <= self::TOLERANCIA) {
                    continue;
                }
                $cuentaCaja = $linea->cuentacajas;
                $bucket = self::bucketMedio(
                    (string) ($cuentaCaja->nombre ?? ''),
                    (string) ($cuentaCaja->codigo ?? ''),
                );
                $medios[$bucket] = round($medios[$bucket] + $monto, 2);
            }
        }

        $suma = $medios['efectivo'] + $medios['tarjeta'] + $medios['cheque']
            + $medios['ctacte'] + $medios['habitacion'];

        // Paridad valida_total_resvta: si medios > total, baja efectivo.
        if ($suma > $totalFactura + self::TOLERANCIA) {
            $dif = round($suma - $totalFactura, 2);
            $medios['efectivo'] = round(max(0, $medios['efectivo'] - $dif), 2);
        }

        // Paridad a-comandera: total ≠ 0 y todos los medios en 0 → efectivo = total.
        if ($totalFactura > self::TOLERANCIA
            && abs($medios['efectivo']) <= self::TOLERANCIA
            && abs($medios['tarjeta']) <= self::TOLERANCIA
            && abs($medios['cheque']) <= self::TOLERANCIA
            && abs($medios['ctacte']) <= self::TOLERANCIA
            && abs($medios['habitacion']) <= self::TOLERANCIA
        ) {
            $medios['efectivo'] = $totalFactura;
        }

        return $medios;
    }

    /**
     * Clasifica cuenta de caja → bucket resvta Crown (efectivo/tarjeta/cheque/ctacte/habitacion).
     */
    public static function bucketMedio(string $nombre, string $codigo = ''): string
    {
        $texto = self::normalizar($nombre.' '.$codigo);

        if (str_contains($texto, 'HABITACION') || str_contains($texto, 'HOTEL') || str_contains($texto, 'CASHLESS')) {
            return 'habitacion';
        }
        if (str_contains($texto, 'CHEQUE')) {
            return 'cheque';
        }
        if (str_contains($texto, 'CTG') || str_contains($texto, 'CANJE TARJETA')
            || str_contains($texto, 'CTA CTE') || str_contains($texto, 'CTACTE')
            || str_contains($texto, 'CUENTA CORRIENTE')) {
            return 'ctacte';
        }
        if (str_contains($texto, 'EFECTIVO') || str_contains($texto, 'CAJA PESOS')
            || str_contains($texto, 'VOUCHER') || str_contains($texto, 'BILLETE')) {
            return 'efectivo';
        }

        // Tarjeta / QR / MP / Fiserv / Totalcoin → resv_tot_tarjeta (schema Crown sin fiserv/qr).
        $etiqueta = GastronomiaCuentacajaIconoSupport::etiquetaBoton($nombre, $codigo);
        $et = self::normalizar($etiqueta);
        if (str_contains($et, 'EFECTIVO')) {
            return 'efectivo';
        }

        return 'tarjeta';
    }

    private static function resolverTipoDto(?GastroCrownCuenta $cuenta): int
    {
        if ($cuenta === null) {
            return self::TIPO_DTO_NINGUNO;
        }

        $tipo = (string) ($cuenta->tipo_descuento ?? '');
        if ($tipo === GastroCrownCuenta::TIPO_DTO_INVITADOS) {
            return self::TIPO_DTO_INVITADOS;
        }
        if ($tipo === GastroCrownCuenta::TIPO_DTO_STAFF) {
            return self::TIPO_DTO_STAFF;
        }
        if ($tipo === GastroCrownCuenta::TIPO_DTO_DESCUENTO) {
            $codigo = (int) preg_replace('/\D+/', '', (string) ($cuenta->descuentoGastronomia->codigo ?? '')) ?: 0;

            return $codigo > 0 ? $codigo : self::TIPO_DTO_DESCUENTO;
        }

        if ((float) ($cuenta->porcentaje_descuento ?? 0) > 0 || (int) ($cuenta->descuento_gastronomia_id ?? 0) > 0) {
            $codigo = (int) preg_replace('/\D+/', '', (string) ($cuenta->descuentoGastronomia->codigo ?? '')) ?: 0;

            return $codigo > 0 ? $codigo : self::TIPO_DTO_DESCUENTO;
        }

        return self::TIPO_DTO_NINGUNO;
    }

    /**
     * @return array{efectivo:float,tarjeta:float,cheque:float,ctacte:float,habitacion:float}
     */
    private static function mediosEnCero(): array
    {
        return [
            'efectivo' => 0.0,
            'tarjeta' => 0.0,
            'cheque' => 0.0,
            'ctacte' => 0.0,
            'habitacion' => 0.0,
        ];
    }

    private static function fechaEntera(string $fecha): int
    {
        try {
            return (int) Carbon::parse($fecha)->format('Ymd');
        } catch (Throwable) {
            return (int) Carbon::now()->format('Ymd');
        }
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'U', 'N'], $texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }
}
