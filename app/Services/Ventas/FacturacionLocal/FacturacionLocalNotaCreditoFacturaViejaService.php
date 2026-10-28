<?php

declare(strict_types=1);

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Stock\Articulo;
use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalCuentaVentaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalReceptorSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Notas de crédito varias: no hay comprobante origen en el ERP.
 * ARCA recibe el período del mes de la nota.
 * El neto va a L-notas de crédito y la contrapartida a la cuenta de valores.
 * No mueve el efectivo del local.
 */
final class FacturacionLocalNotaCreditoFacturaViejaService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    /**
     * @param  array{
     *   fecha?:string,
     *   lineas?:list<array<string,mixed>>
     * }  $input
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_url?:string,mensaje?:string}
     */
    public function emitir(LocalVenta $local, array $input): array
    {
        $turno = $this->turnoService->turnoAbierto((int) $local->id);
        if ($turno === null) {
            return ['ok' => false, 'error' => 'Abrí el turno del local. La nota de crédito entra a los comprobantes de ese día.'];
        }

        $tipoNcId = (int) $local->tipoNcId();
        $tipoNc = Tipotransaccion::query()->find($tipoNcId);
        if (! $tipoNc) {
            return ['ok' => false, 'error' => 'El local no tiene tipo de nota de crédito. Configuralo en Locales.'];
        }
        if ($tipoNc->signo === 'S') {
            return ['ok' => false, 'error' => 'El tipo de nota de crédito del local tiene que tener signo Resta.'];
        }

        $empresaId = (int) ($local->empresa_id ?: 0);
        $cuentaNotasId = FacturacionLocalCuentaVentaSupport::cuentaNotasCreditoId($empresaId);
        if ($cuentaNotasId <= 0) {
            return ['ok' => false, 'error' => 'Falta la cuenta de L-notas de crédito en Cuentas automáticas (Facturación local).'];
        }
        $cuentaValoresId = FacturacionLocalCuentaVentaSupport::cuentaValoresId($empresaId);
        if ($cuentaValoresId <= 0) {
            return ['ok' => false, 'error' => 'Falta la cuenta de valores en Cuentas automáticas (Facturación local).'];
        }

        try {
            $fecha = $this->fechaDelPeriodoActual((string) ($input['fecha'] ?? ''));
            $lineas = $this->normalizarLineas($input['lineas'] ?? []);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $receptor = FacturacionLocalReceptorSupport::resolver([]);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $payload = $this->armarPayload($local, $lineas, $fecha, $tipoNcId, $receptor, $cuentaValoresId);

        try {
            return DB::transaction(function () use ($local, $turno, $payload) {
                $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
                if (! is_array($resultado) || ! empty($resultado['error'])) {
                    throw new InvalidArgumentException(
                        trim((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'No se pudo emitir la nota de crédito.'))
                    );
                }

                $ventaId = (int) ($resultado['venta_id'] ?? 0);
                $ventaNc = $ventaId > 0 ? Venta::query()->find($ventaId) : null;
                if (! $ventaNc) {
                    throw new InvalidArgumentException('No se obtuvo la nota de crédito emitida.');
                }

                $total = abs((float) $ventaNc->total);
                if ($total < 0.01) {
                    throw new InvalidArgumentException('La nota de crédito quedó en cero. Revisá precios y cantidades.');
                }

                FacturacionLocalEmision::query()->create([
                    'local_venta_id' => (int) $local->id,
                    'turno_operativo_local_id' => (int) $turno->id,
                    'venta_id' => (int) $ventaNc->id,
                    'venta_nc_id' => (int) $ventaNc->id,
                    'vale_cliente_local_id' => null,
                    'es_ticket_regalo' => false,
                    'payload_resumen_json' => [
                        'origen' => 'nota_credito_factura_vieja',
                        'periodo_arca' => 'mes_de_la_nota',
                        'usuario_id' => Auth::id(),
                    ],
                ]);

                $this->turnoService->sumarFacturacion($turno, -1 * $total);

                if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                    $this->facturacionService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                }

                $ventaNc->refresh();
                $facturaTxt = trim((string) ($resultado['factura'] ?? $ventaNc->codigo));

                return [
                    'ok' => true,
                    'venta_id' => (int) $ventaNc->id,
                    'factura' => $facturaTxt,
                    'pdf_url' => route('lista_una_factura_pdf', (int) $ventaNc->id),
                    'mensaje' => 'Nota de crédito '.$facturaTxt.' generada. Quedó en los comprobantes del día.',
                ];
            });
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            $msg = VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e)
                ? 'Ya existe un comprobante con ese punto de venta, tipo y número. Reintentá.'
                : $e->getMessage();
            Log::error('facturacion_local.nc_factura_vieja.fallo', [
                'local_id' => $local->id,
                'clase_error' => ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage()),
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $msg];
        }
    }

    private function fechaDelPeriodoActual(string $fecha): Carbon
    {
        $texto = trim($fecha);
        if ($texto === '') {
            return Carbon::today();
        }

        try {
            $dia = Carbon::parse($texto)->startOfDay();
        } catch (Throwable) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }

        $hoy = Carbon::today();
        if ($dia->gt($hoy)) {
            throw new InvalidArgumentException('La fecha no puede ser posterior a hoy.');
        }
        if ($dia->format('Y-m') !== $hoy->format('Y-m')) {
            throw new InvalidArgumentException('La fecha tiene que ser de este mes. ARCA toma el período en curso.');
        }

        return $dia;
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @return list<array<string,mixed>>
     */
    private function normalizarLineas(array $lineas): array
    {
        if ($lineas === []) {
            throw new InvalidArgumentException('Agregá al menos un artículo.');
        }

        $out = [];
        foreach ($lineas as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $articulo = $articuloId > 0 ? Articulo::query()->find($articuloId) : null;
            if (! $articulo || ! ArticuloCanalSupport::articuloOperativoLocal($articuloId)) {
                throw new InvalidArgumentException('Hay un artículo que no está operativo en el canal Local.');
            }

            $variante = FacturacionLocalVarianteArticuloSupport::validarLinea(
                $articulo,
                (int) ($linea['talle_id'] ?? 0),
                (int) ($linea['color_id'] ?? 0),
                (int) ($linea['combinacion_id'] ?? 0),
            );
            if (empty($variante['ok'])) {
                $sku = trim((string) ($articulo->sku ?? ''));
                throw new InvalidArgumentException(($sku !== '' ? $sku.': ' : '').($variante['error'] ?? 'Variante inválida.'));
            }

            $cantidad = abs((float) ($linea['cantidad'] ?? 0));
            $precio = round((float) ($linea['precio'] ?? 0), 2);
            if ($cantidad < 0.001) {
                throw new InvalidArgumentException('La cantidad tiene que ser mayor a cero.');
            }
            if ($precio < 0.01) {
                throw new InvalidArgumentException('El precio tiene que ser mayor a cero. Si la lista cambió, cargá el de la factura vieja.');
            }

            $out[] = [
                'articulo_id' => $articuloId,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'talle_id' => (int) ($variante['talle_id'] ?? 0),
                'color_id' => (int) ($variante['color_id'] ?? 0),
                'combinacion_id' => (int) ($variante['combinacion_id'] ?? 0),
            ];
        }

        if ($out === []) {
            throw new InvalidArgumentException('Agregá al menos un artículo.');
        }

        return $out;
    }

    /**
     * @param  list<array<string,mixed>>  $lineas
     * @param  array<string,mixed>  $receptor
     * @return array<string,mixed>
     */
    private function armarPayload(
        LocalVenta $local,
        array $lineas,
        Carbon $fecha,
        int $tipoNcId,
        array $receptor,
        int $cuentaValoresId,
    ): array {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $combinacionIds = [];
        $talleIds = [];
        $colorIds = [];

        foreach ($lineas as $linea) {
            $articuloIds[] = (int) $linea['articulo_id'];
            $cantidades[] = (float) $linea['cantidad'];
            $precios[] = (float) $linea['precio'];
            $descripciones[] = (string) $linea['descripcion'];
            $combinacionIds[] = (int) $linea['combinacion_id'];
            $talleIds[] = (int) $linea['talle_id'];
            $colorIds[] = (int) $linea['color_id'];
        }

        $letra = strtoupper(trim((string) ($receptor['letra'] ?? 'B')));
        if ($letra === '') {
            $letra = 'B';
        }
        $listaId = (int) ($local->listaprecio_id ?: 0);
        $prepIva = FacturacionLocalPrecioIvaSupport::prepararParaEmision(
            $precios,
            $articuloIds,
            $listaId,
            $letra,
            false,
        );

        $fechaYmd = $fecha->format('Y-m-d');
        $periodoDesde = $fecha->copy()->startOfMonth()->format('Ymd');
        $periodoHasta = $fecha->format('Ymd');
        $puntoventaId = (int) ($local->puntoventaDefaultId() ?? $local->puntoventa_id ?? 0);
        $clienteId = (int) ($receptor['cliente_id'] ?? 0);
        if ($clienteId <= 0) {
            $clienteId = FacturacionLocalReceptorSupport::clienteContadoId();
        }

        $payload = [
            'empresa_id' => (int) ($local->empresa_id ?: 0),
            'puntoventa_id' => $puntoventaId,
            'tipotransaccion_id' => $tipoNcId,
            'cliente_id' => $clienteId,
            'fechafactura' => $fechaYmd,
            'fecha' => $fechaYmd,
            'fechaasignaciondesde' => $periodoDesde,
            'fechaasignacionhasta' => $periodoHasta,
            'deposito_id' => (int) $local->deposito_id,
            'listaprecio_id' => $listaId,
            'moneda_id' => (int) config('facturacion_local.moneda_id', 1),
            'cotizacion' => 1.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $prepIva['precios'],
            'incluyeimpuestos' => $prepIva['incluyeimpuestos'],
            'descuentolinea' => 0,
            'descripciones' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            'descuentopie' => 0,
            'descuentoimportepie' => 0,
            'vendedor_id' => Auth::id(),
            'leyendafactura' => 'Notas de crédito varias. Período ARCA del mes en curso.',
            'opciones_emision' => [
                'omitir_movimiento_stock' => false,
                'forzar_operacion_stock' => TipotransaccionOperacionStockSupport::ENTRADA,
                'permitir_caea' => false,
                'origen_facturacion_local' => true,
                'omitir_cuenta_corriente' => true,
                'omitir_solicitud_arca_cae' => true,
                FacturacionLocalCuentaVentaSupport::FLAG_IMPUTAR_NOTAS_CREDITO => true,
                FacturacionLocalCuentaVentaSupport::FLAG_CONTRAPARTIDA_CUENTA => $cuentaValoresId,
            ],
            'arca_receptor' => $receptor['arca_receptor'] ?? [],
            'venta_receptor' => $receptor['venta_receptor'] ?? [],
        ];

        if (! empty($receptor['omitir_percepciones'])) {
            $payload['omitir_percepciones'] = true;
        }

        $pv = $puntoventaId > 0 ? Puntoventa::query()->find($puntoventaId) : null;
        if (FacturacionLocalPosContextoSupport::pvUsaWebservice($pv)
            && strtoupper(trim((string) ($pv->modofacturacion ?? ''))) === 'M'
        ) {
            $payload['forzar_modofacturacion'] = 'C';
        }

        return $payload;
    }
}
