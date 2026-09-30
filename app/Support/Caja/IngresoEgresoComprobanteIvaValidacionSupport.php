<?php

namespace App\Support\Caja;

use App\Support\Numerico\NumeroDecimalLocalSupport;

/**
 * Cuadre entre comprobantes IVA del IE y el monto del pago en cuentas de caja.
 *
 * Una factura puede ser menor que el pago: la diferencia se imputa al concepto
 * de gasto del movimiento. Los comprobantes no pueden superar el pago.
 */
final class IngresoEgresoComprobanteIvaValidacionSupport
{
    public const TOLERANCIA = 0.05;

    /**
     * @param  list<array<string, mixed>>  $comprobantes
     * @param  list<object|array<string, mixed>>  $lineasCaja  objetos con cuentacaja_ids/montos/moneda_ids/cotizaciones
     */
    public static function validarTotales(array $comprobantes, array $lineasCaja, int $monedaReferenciaId = 1): void
    {
        if ($comprobantes === []) {
            return;
        }

        $totalComprobantes = self::totalComprobantes($comprobantes, $monedaReferenciaId);
        $totalPago = self::totalPagoCaja($lineasCaja, $monedaReferenciaId);

        if ($totalComprobantes <= 0) {
            throw new \RuntimeException('Los comprobantes IVA deben tener total mayor a cero.');
        }

        if ($totalPago <= 0) {
            throw new \RuntimeException('El movimiento de caja debe tener montos para cuadrar con los comprobantes IVA.');
        }

        if ($totalComprobantes - $totalPago > self::TOLERANCIA) {
            throw new \RuntimeException(
                'La suma de comprobantes IVA ('.self::formato($totalComprobantes)
                .') supera el total del pago ('.self::formato($totalPago).').'
            );
        }
    }

    /**
     * Si las facturas no cubren el pago, el resto va al concepto de gasto.
     */
    public static function validarDiferenciaConConceptoGasto(
        float $totalComprobantes,
        float $totalPago,
        int $conceptoGastoId,
    ): void {
        $diferencia = round($totalPago - $totalComprobantes, 2);
        if ($diferencia <= self::TOLERANCIA) {
            return;
        }

        if ($conceptoGastoId > 0) {
            return;
        }

        throw new \RuntimeException(
            'Los comprobantes IVA ('.self::formato($totalComprobantes)
            .') no cubren el pago ('.self::formato($totalPago)
            .'). Indique el concepto de gasto para la diferencia ('.self::formato($diferencia).').'
        );
    }

    private static function formato(float $importe): string
    {
        return number_format($importe, 2, ',', '.');
    }

    /**
     * @param  list<array<string, mixed>>  $comprobantes
     */
    public static function totalComprobantes(array $comprobantes, int $monedaReferenciaId): float
    {
        $total = 0.0;

        foreach ($comprobantes as $comprobante) {
            if (! is_array($comprobante)) {
                continue;
            }

            $monto = round(abs(NumeroDecimalLocalSupport::aFloat($comprobante['total'] ?? 0)), 2);
            if ($monto <= 0) {
                continue;
            }

            $monedaId = (int) ($comprobante['moneda_id'] ?? $monedaReferenciaId);
            $cotizacion = NumeroDecimalLocalSupport::aFloat($comprobante['cotizacion'] ?? 1, 1.0);
            $coef = function_exists('calculaCoeficienteMoneda')
                ? calculaCoeficienteMoneda($monedaReferenciaId, $monedaId, $cotizacion)
                : 1.0;

            $total += $monto * $coef;
        }

        return round($total, 2);
    }

    /**
     * @param  list<object|array<string, mixed>>  $lineasCaja
     */
    public static function totalPagoCaja(array $lineasCaja, int $monedaReferenciaId): float
    {
        $total = 0.0;

        foreach ($lineasCaja as $linea) {
            $montoRaw = is_array($linea) ? ($linea['montos'] ?? $linea['monto'] ?? 0) : ($linea->montos ?? $linea->monto ?? 0);
            $monto = round(abs(NumeroDecimalLocalSupport::aFloat($montoRaw)), 2);
            if ($monto <= 0) {
                continue;
            }

            $monedaId = (int) (is_array($linea) ? ($linea['moneda_ids'] ?? $linea['moneda_id'] ?? $monedaReferenciaId) : ($linea->moneda_ids ?? $linea->moneda_id ?? $monedaReferenciaId));
            $cotRaw = is_array($linea) ? ($linea['cotizaciones'] ?? $linea['cotizacion'] ?? 1) : ($linea->cotizaciones ?? $linea->cotizacion ?? 1);
            $cotizacion = NumeroDecimalLocalSupport::aFloat($cotRaw, 1.0);
            $coef = function_exists('calculaCoeficienteMoneda')
                ? calculaCoeficienteMoneda($monedaReferenciaId, $monedaId, $cotizacion)
                : 1.0;

            $total += $monto * $coef;
        }

        return round($total, 2);
    }
}
