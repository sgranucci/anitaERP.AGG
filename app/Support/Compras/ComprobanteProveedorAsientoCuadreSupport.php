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
     * Netea descuentos negativos (exento E / códigos 80-81) contra líneas DEBE de neto.
     * No crea Haber ni importes negativos: el descuento reduce el Debe de mercadería.
     *
     * @param  list<array<string, mixed>>  $lineasDebe
     * @return list<array<string, mixed>>
     */
    public static function aplicarDescuentoNetoEnDebe(array $lineasDebe, float $descuentoNegativo): array
    {
        $resto = round($descuentoNegativo, 2);
        if ($resto >= -0.0001 || $lineasDebe === []) {
            return $lineasDebe;
        }

        $origenesPreferidos = [
            'neto_manual',
            'anticipo',
            'contrato_manual',
            'oc_articulo',
            'oc_articulo_override',
            'far_diferencia',
        ];
        $indices = [];
        foreach ($lineasDebe as $i => $linea) {
            $origen = (string) ($linea['origen'] ?? '');
            if (in_array($origen, $origenesPreferidos, true)) {
                $indices[] = $i;
            }
        }
        if ($indices === []) {
            foreach ($lineasDebe as $i => $linea) {
                $origen = (string) ($linea['origen'] ?? '');
                if (! in_array($origen, ['impuesto', 'impuesto_interno', 'proveedor', 'far'], true)) {
                    $indices[] = $i;
                }
            }
        }
        if ($indices === []) {
            $indices = array_keys($lineasDebe);
        }

        usort(
            $indices,
            static fn (int $a, int $b): int => ((float) ($lineasDebe[$b]['importe'] ?? 0))
                <=> ((float) ($lineasDebe[$a]['importe'] ?? 0))
        );

        foreach ($indices as $i) {
            if ($resto >= -0.0001) {
                break;
            }
            $importe = round((float) ($lineasDebe[$i]['importe'] ?? 0), 2);
            if ($importe <= 0) {
                continue;
            }
            $nuevo = round($importe + $resto, 2);
            if ($nuevo >= 0.005) {
                $lineasDebe[$i]['importe'] = $nuevo;
                $resto = 0.0;
            } else {
                $resto = round($resto + $importe, 2);
                $lineasDebe[$i]['importe'] = 0.0;
            }
        }

        return array_values(array_filter(
            $lineasDebe,
            static fn (array $linea): bool => abs((float) ($linea['importe'] ?? 0)) >= 0.005
        ));
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
