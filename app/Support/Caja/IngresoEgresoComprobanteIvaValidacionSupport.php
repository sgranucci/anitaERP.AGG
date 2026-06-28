<?php

namespace App\Support\Caja;

/**
 * Cuadre entre comprobantes IVA del IE y el monto del pago en cuentas de caja.
 */
final class IngresoEgresoComprobanteIvaValidacionSupport
{
    private const TOLERANCIA = 0.05;

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

        if (abs($totalComprobantes - $totalPago) > self::TOLERANCIA) {
            throw new \RuntimeException(
                'La suma de comprobantes IVA ('.number_format($totalComprobantes, 2)
                .') no coincide con el total del pago ('.number_format($totalPago, 2).').'
            );
        }
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

            $monto = round(abs((float) ($comprobante['total'] ?? 0)), 2);
            if ($monto <= 0) {
                continue;
            }

            $monedaId = (int) ($comprobante['moneda_id'] ?? $monedaReferenciaId);
            $cotizacion = (float) ($comprobante['cotizacion'] ?? 1);
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
            $monto = round(abs((float) (is_array($linea) ? ($linea['montos'] ?? $linea['monto'] ?? 0) : ($linea->montos ?? $linea->monto ?? 0))), 2);
            if ($monto <= 0) {
                continue;
            }

            $monedaId = (int) (is_array($linea) ? ($linea['moneda_ids'] ?? $linea['moneda_id'] ?? $monedaReferenciaId) : ($linea->moneda_ids ?? $linea->moneda_id ?? $monedaReferenciaId));
            $cotizacion = (float) (is_array($linea) ? ($linea['cotizaciones'] ?? $linea['cotizacion'] ?? 1) : ($linea->cotizaciones ?? $linea->cotizacion ?? 1));
            $coef = function_exists('calculaCoeficienteMoneda')
                ? calculaCoeficienteMoneda($monedaReferenciaId, $monedaId, $cotizacion)
                : 1.0;

            $total += $monto * $coef;
        }

        return round($total, 2);
    }
}
