<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\DescuentoGastronomia;
use App\Services\Ventas\FacturacionService;
use InvalidArgumentException;

/**
 * Facturación de cuentas gastronómicas: sin OT/pedido_combinacion, descuentos de cabecera
 * y reglas de cortesía (100 % → total $0,01, sin cobranza).
 */
final class GastronomiaFacturacionService
{
    public const IMPORTE_MINIMO_FACTURA = 0.01;

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly GastronomiaJornadaService $jornadaService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload  Datos de emisión (tipotransaccion, PV, ítems, receptor ARCA, etc.)
     * @return array{factura?:string,error?:string,sin_cobranza?:bool,factura_cortesia_total?:bool}
     */
    public function emitirComprobante(array $payload, CuentaGastronomia $cuenta): array
    {
        $cuenta->loadMissing(['lineas', 'descuentoGastronomia']);

        $contextoDescuento = $this->evaluarDescuentoCabecera(
            $cuenta,
            $payload['articulo_ids'] ?? [],
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

        $opciones = $this->opcionesEmisionGastronomia();
        $opciones['fechajornada'] = $payload['fechajornada'];
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
        }

        if (isset($resultado['venta_id'])) {
            $resultado['venta_id'] = (int) $resultado['venta_id'];
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $articuloIds
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
    /**
     * Calcula total del comprobante sin grabar (preflight cobranza).
     *
     * @param  array<string, mixed>  $payload
     * @return array{total:float,sin_cobranza:bool,factura_cortesia:bool,error?:string}
     */
    public function previewTotalesEmision(array $payload, CuentaGastronomia $cuenta): array
    {
        $cuenta->loadMissing(['lineas', 'descuentoGastronomia']);

        $contexto = $this->evaluarDescuentoCabecera(
            $cuenta,
            $payload['articulo_ids'] ?? [],
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
                ? max(self::IMPORTE_MINIMO_FACTURA, round($total, 2))
                : $total,
            'sin_cobranza' => $contexto['factura_cortesia_total'],
            'factura_cortesia' => $contexto['factura_cortesia_total'],
        ];
    }

    public function evaluarDescuentoCabecera(
        CuentaGastronomia $cuenta,
        array $articuloIds,
        array $cantidades,
        array $precios,
    ): array {
        $subtotal = $this->subtotalLineas($articuloIds, $cantidades, $precios);
        $d = $cuenta->descuentoGastronomia;

        $descuentopie = 0.;
        $descuentoimportepie = 0.;
        $facturaCortesia = false;

        if ($d instanceof DescuentoGastronomia) {
            if ($d->tipovalor === DescuentoGastronomia::TIPO_PORCENTAJE) {
                $pct = (float) $d->valor;
                if ($pct >= 100.) {
                    $facturaCortesia = true;
                    $descuentopie = $subtotal > self::IMPORTE_MINIMO_FACTURA
                        ? 100. * (1. - self::IMPORTE_MINIMO_FACTURA / $subtotal)
                        : 0.;
                } else {
                    $descuentopie = $pct;
                }
            } elseif ($d->tipovalor === DescuentoGastronomia::TIPO_IMPORTE) {
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

    /**
     * Descuento total (100 % o importe que cubre el subtotal): conserva todos los ítems de la cuenta,
     * ajusta el descuento de pie para dejar total fiscal mínimo ($0,01) y fuerza IVA exento por renglón.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarReglasCortesiaEnPayload(array $payload): array
    {
        $impuestoExentoId = (int) config('gastronomia.impuesto_exento_id', 1);
        $n = count($payload['articulo_ids'] ?? []);

        if ($n > 0) {
            $payload['impuesto_ids'] = array_fill(0, $n, $impuestoExentoId);
            $payload['incluyeimpuestos'] = array_fill(0, $n, 'N');
        }

        $leyendaExtra = ' Consumo bonificado (descuento 100%).';
        $payload['leyendafactura'] = trim((string) ($payload['leyendafactura'] ?? '')).$leyendaExtra;
        $payload['factura_cortesia_total'] = true;

        return $payload;
    }

    /**
     * Gastronomía cobra en el momento: no se registra deuda en cuenta corriente del cliente.
     *
     * @return array<string, bool>
     */
    private function opcionesEmisionGastronomia(): array
    {
        return [
            'omitir_movimiento_stock' => true,
            'omitir_contabilidad' => ! config('gastronomia.genera_contabilidad_al_facturar', true),
            'omitir_cuenta_corriente' => true,
            'omitir_sincronizacion_anita' => ! config('gastronomia.sincronizar_anita_al_facturar', true),
            // CAE al final del proceso gastronómico (misma transacción que cobranza e ingredientes).
            'omitir_solicitud_arca_cae' => true,
        ];
    }

    /**
     * Solicita CAE/CAEA en ARCA para una venta ya grabada (último paso de emisión gastronomía).
     *
     * @param  array<string, mixed>  $caePendiente  Contexto devuelto por generaComprobanteGeneral (cae_pendiente).
     */
    public function completarSolicitudCaePendiente(array $caePendiente): void
    {
        $this->facturacionService->completarSolicitudCaePendiente($caePendiente);
    }

    /**
     * Garantiza venta.nombre = CONSUMIDOR FINAL (u otro configurado) cuando la cuenta no tiene cliente de facturación.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function asegurarVentaReceptorSinClienteMaestro(array $payload, CuentaGastronomia $cuenta): array
    {
        $receptor = app(GastronomiaReceptorFacturacionService::class);
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
     * Sin cliente de facturación en la cuenta: no percepciones (IVA/IIBB); condición IIBB "No percibe".
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarReglasImpuestoConsumidorFinal(array $payload, CuentaGastronomia $cuenta): array
    {
        if (! app(GastronomiaReceptorFacturacionService::class)->facturaComoConsumidorFinal($cuenta)) {
            return $payload;
        }

        $payload['omitir_percepciones'] = true;

        return $payload;
    }

    /**
     * @param  list<int>  $articuloIds
     * @param  list<float|int|string>  $cantidades
     * @param  list<float|int|string>  $precios
     */
    private function subtotalLineas(array $articuloIds, array $cantidades, array $precios): float
    {
        $sub = 0.;
        $n = min(count($articuloIds), count($cantidades), count($precios));
        for ($i = 0; $i < $n; $i++) {
            if ((int) $articuloIds[$i] <= 0) {
                continue;
            }
            $sub += (float) $cantidades[$i] * (float) $precios[$i];
        }

        return round(max(0., $sub), 2);
    }

}
