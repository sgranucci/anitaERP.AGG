<?php

namespace App\Support\Compras;

use App\Support\Configuracion\CotizacionVigenteSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Motor de moneda de las facturas de proveedor. Punto único de conversión.
 *
 * Reglas del negocio (AGG):
 *
 * 1. **Manda la moneda de la factura.** El asiento, la cuenta corriente y Anita se arman en
 *    la moneda del comprobante; todo importe de otra fuente (COM/recepción, cuota, OC) se
 *    convierte a esa moneda antes de sumarse.
 * 2. **Cada importe se convierte con la cotización de su propio documento.** Una COM en dólares
 *    vale ME × cotización de la COM (no la de la factura), porque ese es el valor con el que
 *    se provisionó. Recién ese valor en pesos se lleva a la moneda de la factura.
 * 3. **La moneda de la OC no define importes.** Solo elige si la contrapartida es la cuenta de
 *    proveedores moneda nacional o moneda extranjera (ver ProveedorCuentaContableMonedaSupport).
 * 4. **Una cotización 0 ó 1 en moneda extranjera no es una cotización.** Antes se degradaba a 1
 *    y el dólar terminaba contabilizado como peso. Ahora se resuelve la vigente de la fecha del
 *    documento y, si la moneda no tiene ninguna cargada, se corta con error.
 */
final class ComprobanteProveedorMonedaMotor
{
    /** Una cotización de ME debe superar 1: por debajo es "sin dato", no paridad. */
    public const COTIZACION_MINIMA = 1.0001;

    /** Factor mínimo para sospechar que los importes están en otra moneda. */
    private const FACTOR_SOSPECHA_MINIMO = 10.0;

    public static function esMonedaExtranjera(int $monedaId): bool
    {
        return ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaId);
    }

    public static function normalizarMonedaId(mixed $monedaId): int
    {
        return max(1, (int) $monedaId);
    }

    /**
     * Cotización a usar para un documento (pesos por unidad de moneda extranjera).
     *
     * La grabada en el documento manda; si viene nula, 0 ó 1 en ME se resuelve la vigente
     * de la fecha (nunca se asume 1).
     *
     * @throws RuntimeException cuando la moneda extranjera no tiene ninguna cotización cargada
     */
    public static function cotizacionValida(
        int $monedaId,
        mixed $cotizacionDocumento,
        string|Carbon|null $fecha,
        string $contexto,
    ): float {
        $monedaId = self::normalizarMonedaId($monedaId);
        if (! self::esMonedaExtranjera($monedaId)) {
            return 1.0;
        }

        $cotizacion = (float) ($cotizacionDocumento ?? 0);
        if ($cotizacion > self::COTIZACION_MINIMA) {
            return $cotizacion;
        }

        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($vigente > self::COTIZACION_MINIMA) {
            Log::warning('comprobante_proveedor.cotizacion_repuesta', [
                'contexto' => $contexto,
                'moneda_id' => $monedaId,
                'cotizacion_documento' => $cotizacion,
                'cotizacion_vigente' => $vigente,
                'fecha' => is_string($fecha) ? $fecha : (string) $fecha,
            ]);

            return $vigente;
        }

        throw new RuntimeException(
            'No hay cotización válida para '.$contexto.' (moneda id '.$monedaId.'). '
            .'Cargue la cotización del día o corrija la moneda del documento: '
            .'sin cotización el importe en moneda extranjera se contabilizaría como pesos.'
        );
    }

    /**
     * Lleva un importe a moneda nacional con la cotización del propio documento.
     *
     * @throws RuntimeException
     */
    public static function aMonedaLocal(
        float $importe,
        int $monedaId,
        mixed $cotizacion,
        string|Carbon|null $fecha,
        string $contexto,
    ): float {
        $monedaId = self::normalizarMonedaId($monedaId);
        if (! self::esMonedaExtranjera($monedaId)) {
            return round($importe, 2);
        }

        return round($importe * self::cotizacionValida($monedaId, $cotizacion, $fecha, $contexto), 2);
    }

    /**
     * Convierte un importe de la moneda de su documento a la moneda de la factura.
     *
     * Misma moneda → importe sin tocar (el nominal ya es comparable, aunque las cotizaciones
     * difieran). Distinta moneda → se pasa por moneda nacional: origen × cot. origen ÷ cot. destino.
     *
     * @throws RuntimeException
     */
    public static function convertir(
        float $importe,
        int $monedaOrigenId,
        mixed $cotizacionOrigen,
        string|Carbon|null $fechaOrigen,
        int $monedaDestinoId,
        mixed $cotizacionDestino,
        string|Carbon|null $fechaDestino,
        string $contextoOrigen,
        string $contextoDestino,
    ): float {
        $origenId = self::normalizarMonedaId($monedaOrigenId);
        $destinoId = self::normalizarMonedaId($monedaDestinoId);
        $importe = round($importe, 2);

        if ($origenId === $destinoId || abs($importe) < 0.005) {
            return $importe;
        }

        $enPesos = self::aMonedaLocal($importe, $origenId, $cotizacionOrigen, $fechaOrigen, $contextoOrigen);

        if (! self::esMonedaExtranjera($destinoId)) {
            return $enPesos;
        }

        $cotDestino = self::cotizacionValida($destinoId, $cotizacionDestino, $fechaDestino, $contextoDestino);

        return round($enPesos / $cotDestino, 2);
    }

    /**
     * Igual que convertir(), pero no corta el flujo: para listados y comparaciones de pantalla.
     * Si falta cotización deja el importe como está y lo registra en el log.
     */
    public static function convertirTolerante(
        float $importe,
        int $monedaOrigenId,
        mixed $cotizacionOrigen,
        string|Carbon|null $fechaOrigen,
        int $monedaDestinoId,
        mixed $cotizacionDestino,
        string|Carbon|null $fechaDestino,
        string $contextoOrigen,
        string $contextoDestino,
    ): float {
        try {
            return self::convertir(
                $importe,
                $monedaOrigenId,
                $cotizacionOrigen,
                $fechaOrigen,
                $monedaDestinoId,
                $cotizacionDestino,
                $fechaDestino,
                $contextoOrigen,
                $contextoDestino,
            );
        } catch (RuntimeException $e) {
            Log::warning('comprobante_proveedor.conversion_sin_cotizacion', [
                'origen' => $contextoOrigen,
                'destino' => $contextoDestino,
                'moneda_origen_id' => $monedaOrigenId,
                'moneda_destino_id' => $monedaDestinoId,
                'importe' => $importe,
                'error' => $e->getMessage(),
            ]);

            return round($importe, 2);
        }
    }

    /**
     * Corta cuando el importe de la factura no puede estar en la moneda declarada.
     *
     * Caso real: factura marcada en dólares con los importes tipeados en pesos. Contra la
     * provisión COM la diferencia es del orden de la cotización y, sin este control, se
     * prorrateaba como "diferencia de precio": el asiento quedaba ~1500 veces inflado.
     *
     * Ambos importes llegan ya expresados en la moneda de la factura, así que un cociente
     * del orden de la cotización solo puede venir de una moneda mal declarada.
     *
     * @param  float  $cotizacionEscala  mayor cotización en juego (factura o documento de referencia)
     *
     * @throws RuntimeException
     */
    public static function assertImportesCoherentes(
        float $importeFactura,
        float $importeReferencia,
        int $monedaFacturaId,
        float $cotizacionEscala,
        string $etiquetaReferencia,
        string $monedaFacturaNombre = '',
    ): void {
        if (abs($importeReferencia) < 0.005 || abs($importeFactura) < 0.005) {
            return;
        }

        if ($cotizacionEscala < self::FACTOR_SOSPECHA_MINIMO) {
            return;
        }

        $factor = abs($importeFactura) / abs($importeReferencia);
        $umbral = $cotizacionEscala / 2;
        $etiquetaMoneda = $monedaFacturaNombre !== ''
            ? $monedaFacturaNombre
            : (self::esMonedaExtranjera($monedaFacturaId) ? 'moneda extranjera' : 'pesos');

        if ($factor < $umbral && (1 / $factor) < $umbral) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Moneda incoherente: la factura está declarada en %s y su importe (%s) es %s veces %s %s (%s), '
            .'del orden de la cotización (%s). Corrija la moneda o la cotización de la factura antes de contabilizar: '
            .'los importes de ambos documentos tienen que estar en la misma moneda.',
            $etiquetaMoneda,
            number_format($importeFactura, 2, ',', '.'),
            number_format($factor >= $umbral ? $factor : 1 / $factor, 2, ',', '.'),
            $factor >= $umbral ? 'mayor que' : 'menor que',
            $etiquetaReferencia,
            number_format($importeReferencia, 2, ',', '.'),
            number_format($cotizacionEscala, 2, ',', '.'),
        ));
    }
}
