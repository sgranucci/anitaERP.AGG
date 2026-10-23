<?php

namespace App\Support\Caja;

use App\Support\Numerico\NumeroDecimalLocalSupport;

/**
 * Cuadre entre comprobantes IVA del IE y el monto del pago en cuentas de caja.
 *
 * Los comprobantes de la solapa no tienen que igualar el pago. Lo que no es
 * factura (por ejemplo L-VISA, que junta posnets ya facturados) puede quedar
 * solo en el asiento, como en tesmov. Si el asiento ya balancea el pago entero,
 * no se pide concepto de gasto ni un comprobante para esa diferencia.
 * Si no hay asiento que cubra el pago, la diferencia sigue yendo al concepto
 * de gasto. Los comprobantes no pueden superar el pago.
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
     * El asiento manual ya explica el pago (Debe = Haber = pago). La diferencia
     * contra los comprobantes IVA es movimiento solo contable.
     *
     * @param  list<mixed>  $debes
     * @param  list<mixed>  $haberes
     * @param  list<mixed>  $monedaIds
     * @param  list<mixed>  $cotizaciones
     */
    public static function asientoCubrePago(
        array $debes,
        array $haberes,
        array $monedaIds,
        array $cotizaciones,
        float $totalPago,
        int $monedaReferenciaId,
    ): bool {
        if ($totalPago <= self::TOLERANCIA) {
            return false;
        }

        $n = max(count($debes), count($haberes), count($monedaIds), count($cotizaciones));
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $debe = round(NumeroDecimalLocalSupport::aFloat($debes[$i] ?? 0), 2);
            $haber = round(NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? 0), 2);
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $monedaId = (int) ($monedaIds[$i] ?? $monedaReferenciaId);
            if ($monedaId <= 0) {
                $monedaId = $monedaReferenciaId > 0 ? $monedaReferenciaId : 1;
            }
            $cotizacion = NumeroDecimalLocalSupport::aFloat($cotizaciones[$i] ?? 1, 1.0);
            $coef = function_exists('calculaCoeficienteMoneda')
                ? calculaCoeficienteMoneda($monedaReferenciaId, $monedaId, $cotizacion)
                : 1.0;

            if ($debe > 0) {
                $totalDebe += $debe * $coef;
            }
            if ($haber > 0) {
                $totalHaber += $haber * $coef;
            }
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);
        if ($totalDebe <= self::TOLERANCIA || $totalHaber <= self::TOLERANCIA) {
            return false;
        }
        if (abs($totalDebe - $totalHaber) > self::TOLERANCIA) {
            return false;
        }

        return abs(max($totalDebe, $totalHaber) - $totalPago) <= self::TOLERANCIA;
    }

    /**
     * Si las facturas no cubren el pago y el asiento tampoco explica esa
     * diferencia, el resto va al concepto de gasto.
     */
    public static function validarDiferenciaConConceptoGasto(
        float $totalComprobantes,
        float $totalPago,
        int $conceptoGastoId,
        bool $asientoCubrePago = false,
    ): void {
        $diferencia = round($totalPago - $totalComprobantes, 2);
        if ($diferencia <= self::TOLERANCIA) {
            return;
        }

        if ($asientoCubrePago || $conceptoGastoId > 0) {
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
