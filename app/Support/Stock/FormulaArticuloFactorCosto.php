<?php

namespace App\Support\Stock;

/**
 * Factor costo de línea de fórmula: en BD suele venir "0.00" (string) desde Anita;
 * para cálculos se interpreta como 1 cuando es cero o vacío.
 */
final class FormulaArticuloFactorCosto
{
    public static function efectivo(mixed $factorcosto): float
    {
        if ($factorcosto === null || $factorcosto === '') {
            return 1.0;
        }

        $fc = (float) $factorcosto;

        return $fc != 0.0 ? $fc : 1.0;
    }
}
