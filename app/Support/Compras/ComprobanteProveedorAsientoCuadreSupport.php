<?php

namespace App\Support\Compras;

/**
 * Cuadre de centavos y diferencia de precio factura vs COM.
 *
 * Anita exige Debe = Haber (tol. 0,009). Un desvío de 0,01–0,05 que no se imputa
 * deja el asiento desbalanceado (p. ej. FGA #48: COM 110015,67 vs neto 110015,70).
 *
 * Diferencia de precio / redondeo vs COM: hasta TOLERANCIA_PCT sobre la provisión
 * se prorratea a las cuentas de los artículos de la COM (no se detiene la grabación).
 */
final class ComprobanteProveedorAsientoCuadreSupport
{
    /** Desvío máximo de centavos Debe vs Haber (conceptos vs total) antes de rechazar. */
    public const TOLERANCIA = 0.05;

    /** Diferencia neta factura vs COM absorbible en cuentas de artículos (% sobre provisión). */
    public const TOLERANCIA_PCT = 5.0;

    /** A partir de 1 centavo hay que imputar la diferencia (no tragarla). */
    public const MIN_CENTAVO = 0.01;

    public static function hayDiferenciaAImputar(float $diferencia): bool
    {
        return abs(round($diferencia, 2)) >= self::MIN_CENTAVO;
    }

    public static function porcentajeDiferencia(float $diferencia, float $base): float
    {
        $baseAbs = abs($base);
        if ($baseAbs < self::MIN_CENTAVO) {
            return abs(round($diferencia, 2)) >= self::MIN_CENTAVO ? 100.0 : 0.0;
        }

        return abs(round($diferencia, 2)) / $baseAbs * 100.0;
    }

    public static function diferenciaDentroDePorcentaje(
        float $diferencia,
        float $base,
        float $porcentajeMax = self::TOLERANCIA_PCT,
    ): bool {
        return self::porcentajeDiferencia($diferencia, $base) <= $porcentajeMax + 0.000001;
    }

    /**
     * Suma $ajuste a una línea DEBE. Evita la cuenta excluida (provisión FAR)
     * para no dejar saldo residual contra el asiento de la COM.
     *
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>  $lineasDebe
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>
     */
    public static function absorberCentavosEnDebe(
        array $lineasDebe,
        float $ajuste,
        int $cuentaExcluidaId = 0,
    ): array {
        $ajuste = round($ajuste, 2);
        if (! self::hayDiferenciaAImputar($ajuste) || abs($ajuste) > self::TOLERANCIA) {
            return $lineasDebe;
        }

        for ($i = count($lineasDebe) - 1; $i >= 0; $i--) {
            if ($cuentaExcluidaId > 0 && (int) ($lineasDebe[$i]['cuentacontable_id'] ?? 0) === $cuentaExcluidaId) {
                continue;
            }

            $nuevo = round((float) ($lineasDebe[$i]['importe'] ?? 0) + $ajuste, 2);
            if ($nuevo > 0) {
                $lineasDebe[$i]['importe'] = $nuevo;

                return $lineasDebe;
            }
        }

        $ultimo = count($lineasDebe) - 1;
        if ($ultimo >= 0) {
            $lineasDebe[$ultimo]['importe'] = round((float) ($lineasDebe[$ultimo]['importe'] ?? 0) + $ajuste, 2);
        }

        return $lineasDebe;
    }
}
