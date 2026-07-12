<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use App\Support\Caja\Estacionamiento\EstacionamientoFacturaPayloadSupport;
use App\Services\Ventas\FacturacionService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Facturación de cuentas estacionamiento: descuentos de cabecera y reglas de cortesía.
 */
final class EstacionamientoFacturacionService
{
    public const IMPORTE_MINIMO_FACTURA = 0.01;

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly JornadaEstacionamientoService $jornadaService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{factura?:string,error?:string,sin_cobranza?:bool,factura_cortesia_total?:bool,venta_id?:int}
     */
    public function emitirComprobante(array $payload, CuentaEstacionamiento $cuenta): array
    {
        $cuenta->loadMissing(['lineas', 'descuentoEstacionamiento']);

        $contextoDescuento = $this->evaluarDescuentoCabecera(
            $cuenta,
            $payload['cantidades'] ?? [],
            $payload['precios'] ?? [],
        );

        $payload['descuentopie'] = $contextoDescuento['descuentopie'];
        $payload['descuentoimportepie'] = $contextoDescuento['descuentoimportepie'];

        if ($contextoDescuento['factura_cortesia_total']) {
            $payload = $this->aplicarReglasCortesiaEnPayload($payload);
        }

        try {
            $payload = $this->jornadaService->aplicarFechasAlPayload($payload, (int) $cuenta->empresa_id);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage(), 'mensaje' => $e->getMessage()];
        }

        $opciones = $this->opcionesEmisionEstacionamiento();
        $opciones['fechajornada'] = $payload['fechajornada'];
        if (! empty($payload['_omitir_numera_anita_fin'])) {
            $opciones['omitir_numera_anita_fin'] = true;
            unset($payload['_omitir_numera_anita_fin']);
        }
        $payload['opciones_emision'] = $opciones;
        $payload = $this->asegurarVentaReceptorSinClienteMaestro($payload, $cuenta);
        $payload = $this->aplicarReglasImpuestoConsumidorFinal($payload, $cuenta);

        $resultado = $this->facturacionService->generaComprobanteGeneral($payload);

        if (! is_array($resultado)) {
            return ['error' => 'Respuesta inesperada del servicio de facturación.'];
        }

        if (! empty($resultado['error'])) {
            $mensaje = trim((string) ($resultado['mensaje'] ?? $resultado['error']));

            return [
                'error' => $mensaje,
                'mensaje' => $mensaje,
            ];
        }

        if ($contextoDescuento['sin_cobranza']) {
            $resultado['sin_cobranza'] = true;
        }
        if ($contextoDescuento['factura_cortesia_total']) {
            $resultado['factura_cortesia_total'] = true;
            $this->normalizarTotalCortesia($resultado);
        }

        if (isset($resultado['venta_id'])) {
            $resultado['venta_id'] = (int) $resultado['venta_id'];
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{total:float,sin_cobranza:bool,factura_cortesia:bool,error?:string}
     */
    public function previewTotalesEmision(array $payload, CuentaEstacionamiento $cuenta): array
    {
        $cuenta->loadMissing(['lineas', 'descuentoEstacionamiento']);

        $contexto = $this->evaluarDescuentoCabecera(
            $cuenta,
            $payload['cantidades'] ?? [],
            $payload['precios'] ?? [],
        );

        $payload['descuentopie'] = $contexto['descuentopie'];
        $payload['descuentoimportepie'] = $contexto['descuentoimportepie'];
        $payload['descuentolinea'] = $payload['descuentolinea'] ?? 0.;

        if ($contexto['factura_cortesia_total']) {
            $payload = $this->aplicarReglasCortesiaEnPayload($payload);
        }

        try {
            $payload = $this->jornadaService->aplicarFechasAlPayload($payload, (int) $cuenta->empresa_id);
        } catch (InvalidArgumentException $e) {
            return [
                'total' => 0.,
                'sin_cobranza' => $contexto['factura_cortesia_total'],
                'factura_cortesia' => $contexto['factura_cortesia_total'],
                'error' => $e->getMessage(),
            ];
        }

        $payload = $this->aplicarReglasImpuestoConsumidorFinal($payload, $cuenta);

        $calculo = $this->facturacionService->calculaFacturaGeneral($payload);

        if (isset($calculo['error'])) {
            return [
                'total' => 0.,
                'sin_cobranza' => $contexto['factura_cortesia_total'],
                'factura_cortesia' => $contexto['factura_cortesia_total'],
                'error' => (string) $calculo['error'],
            ];
        }

        $total = (float) ($calculo['totalcomprobante'] ?? 0);

        return [
            'total' => $contexto['factura_cortesia_total']
                ? self::IMPORTE_MINIMO_FACTURA
                : $total,
            'sin_cobranza' => $contexto['factura_cortesia_total'],
            'factura_cortesia' => $contexto['factura_cortesia_total'],
        ];
    }

    /**
     * @param  list<float|int|string>  $cantidades
     * @param  list<float|int|string>  $precios
     * @return array{
     *   descuentopie:float,
     *   descuentoimportepie:float,
     *   factura_cortesia_total:bool,
     *   sin_cobranza:bool,
     *   subtotal_lineas:float
     * }
     */
    public function evaluarDescuentoCabecera(
        CuentaEstacionamiento $cuenta,
        array $cantidades,
        array $precios,
    ): array {
        $subtotal = $this->subtotalLineas($cantidades, $precios);
        $d = $cuenta->descuentoEstacionamiento;

        $descuentopie = 0.;
        $descuentoimportepie = 0.;
        $facturaCortesia = false;

        if ($d instanceof DescuentoEstacionamiento) {
            if ($d->tipovalor === DescuentoEstacionamiento::TIPO_PORCENTAJE) {
                $pct = (float) $d->valor;
                if ($pct >= 100.) {
                    $facturaCortesia = true;
                    $descuentopie = $subtotal > self::IMPORTE_MINIMO_FACTURA
                        ? 100. * (1. - self::IMPORTE_MINIMO_FACTURA / $subtotal)
                        : 0.;
                } else {
                    $descuentopie = $pct;
                }
            } elseif ($d->tipovalor === DescuentoEstacionamiento::TIPO_IMPORTE) {
                $importe = (float) $d->valor;
                if ($subtotal > 0. && $importe >= $subtotal - 0.001) {
                    $facturaCortesia = true;
                    $descuentoimportepie = max(0., $subtotal - self::IMPORTE_MINIMO_FACTURA);
                } else {
                    $descuentoimportepie = $importe;
                }
            }
        }

        return [
            'descuentopie' => $descuentopie,
            'descuentoimportepie' => $descuentoimportepie,
            'factura_cortesia_total' => $facturaCortesia,
            'sin_cobranza' => $facturaCortesia,
            'subtotal_lineas' => $subtotal,
        ];
    }

    public function revertirVentaEnAnitaSiHabilitado(?Venta $venta): void
    {
        if (! $venta || ! config('estacionamiento.sincronizar_anita_al_facturar', false)) {
            return;
        }

        try {
            $this->facturacionService->borraAnitaDesdeVenta($venta);
        } catch (Throwable $e) {
            Log::error('estacionamiento.revertir_anita.fallo', [
                'venta_id' => $venta->id,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $anitaPendiente
     */
    public function ejecutarAnitaPendienteEstacionamiento(array $anitaPendiente): void
    {
        $this->facturacionService->ejecutarAnitaPendienteGastronomia($anitaPendiente);
    }

    /**
     * Ejecuta o encola la réplica Anita diferida tras commit (factura o NC estacionamiento).
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function completarAnitaPendienteTrasEmision(array $resultado): array
    {
        if ($this->debeEncolarAnitaTrasRespuesta($resultado)) {
            $this->encolarAnitaTrasRespuesta($resultado);
            unset($resultado['anita_pendiente']);

            return $resultado;
        }

        if (! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente'])) {
            try {
                $this->ejecutarAnitaPendienteEstacionamiento($resultado['anita_pendiente']);
            } catch (Throwable $e) {
                Log::error('estacionamiento.anita.post_commit.fallo', [
                    'venta_id' => $resultado['venta_id'] ?? null,
                    'msg' => $e->getMessage(),
                ]);
                $aviso = 'Comprobante emitido; falló la replicación en Anita.';
                $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
                $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$aviso : $aviso;
            }
            unset($resultado['anita_pendiente']);
        }

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function debeEncolarAnitaTrasRespuesta(array $resultado): bool
    {
        if (! filter_var(config('estacionamiento.anita_tras_respuesta', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! config('estacionamiento.sincronizar_anita_al_facturar', false)) {
            return false;
        }

        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return false;
        }

        return ! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente']);
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function encolarAnitaTrasRespuesta(array $resultado): void
    {
        $anitaPendiente = $resultado['anita_pendiente'];
        $ventaId = (int) ($resultado['venta_id'] ?? 0);

        if (! is_array($anitaPendiente)) {
            return;
        }

        app()->terminating(function () use ($anitaPendiente, $ventaId): void {
            try {
                $this->ejecutarAnitaPendienteEstacionamiento($anitaPendiente);
            } catch (Throwable $e) {
                Log::error('estacionamiento.anita.defer.fallo', [
                    'venta_id' => $ventaId,
                    'msg' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $caePendiente
     * @return array<string, mixed>|null
     */
    public function completarSolicitudCaePendiente(array $caePendiente): ?array
    {
        return $this->facturacionService->completarSolicitudCaePendiente($caePendiente, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarReglasCortesiaEnPayload(array $payload): array
    {
        $impuestoExentoId = EstacionamientoFacturaPayloadSupport::impuestoExentoId();
        $n = count($payload['cantidades'] ?? []);

        if ($n > 0) {
            $payload['impuesto_ids'] = array_fill(0, $n, $impuestoExentoId);
            $payload['incluyeimpuestos'] = array_fill(0, $n, 'N');
        }

        $leyendaExtra = ' Estacionamiento bonificado (descuento 100%).';
        $payload['leyendafactura'] = trim((string) ($payload['leyendafactura'] ?? '')).$leyendaExtra;
        $payload['factura_cortesia_total'] = true;

        return $payload;
    }

    /**
     * @return array<string, bool>
     */
    private function opcionesEmisionEstacionamiento(): array
    {
        return [
            'omitir_movimiento_stock' => true,
            'omitir_contabilidad' => ! config('estacionamiento.genera_contabilidad_al_facturar', false),
            'omitir_cuenta_corriente' => true,
            'omitir_sincronizacion_anita' => ! config('estacionamiento.sincronizar_anita_al_facturar', false),
            'anita_modo_minimo' => true,
            'omitir_solicitud_arca_cae' => true,
            'omitir_stkmov_anita' => true,
            'origen_estacionamiento' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function asegurarVentaReceptorSinClienteMaestro(array $payload, CuentaEstacionamiento $cuenta): array
    {
        $receptor = app(EstacionamientoReceptorFacturacionService::class);
        if (! $receptor->facturaComoConsumidorFinal($cuenta)) {
            return $payload;
        }

        $nombre = trim((string) ($payload['venta_receptor']['nombre'] ?? ''));
        if ($nombre === '') {
            $payload['venta_receptor'] = $receptor->datosVentaReceptorConsumidorFinal();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarReglasImpuestoConsumidorFinal(array $payload, CuentaEstacionamiento $cuenta): array
    {
        if (! app(EstacionamientoReceptorFacturacionService::class)->facturaComoConsumidorFinal($cuenta)) {
            return $payload;
        }

        $payload['omitir_percepciones'] = true;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function normalizarTotalCortesia(array &$resultado): void
    {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        if (! $venta) {
            return;
        }

        $totalActual = round((float) $venta->total, 2);
        $exceso = round($totalActual - self::IMPORTE_MINIMO_FACTURA, 2);
        if ($exceso <= 0.) {
            return;
        }

        $venta->total = self::IMPORTE_MINIMO_FACTURA;
        $venta->save();

        $exento = Venta_Impuesto::query()
            ->where('venta_id', $ventaId)
            ->where('concepto', 'Exento')
            ->first();
        if ($exento instanceof Venta_Impuesto) {
            $exento->importe = round(max(0., (float) $exento->importe - $exceso), 2);
            $exento->save();
        }

        if (isset($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
            $dataCae = $resultado['cae_pendiente']['data_cae'] ?? null;
            if (is_array($dataCae)) {
                $dataCae['total'] = self::IMPORTE_MINIMO_FACTURA;
                $dataCae['exento'] = round(max(0., (float) ($dataCae['exento'] ?? 0) - $exceso), 2);
                $resultado['cae_pendiente']['data_cae'] = $dataCae;
            }
        }
    }

    /**
     * @param  list<float|int|string>  $cantidades
     * @param  list<float|int|string>  $precios
     */
    private function subtotalLineas(array $cantidades, array $precios): float
    {
        $sub = 0.;
        $n = min(count($cantidades), count($precios));
        for ($i = 0; $i < $n; $i++) {
            $sub += (float) $cantidades[$i] * (float) $precios[$i];
        }

        return round(max(0., $sub), 2);
    }
}
