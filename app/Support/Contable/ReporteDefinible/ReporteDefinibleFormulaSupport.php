<?php

namespace App\Support\Contable\ReporteDefinible;

/**
 * Evalúa fórmulas simples entre rubros: R001+R002-R003 (códigos de línea).
 */
class ReporteDefinibleFormulaSupport
{
    /**
     * @param  array<string, float>  $valoresPorCodigoLinea  ej. ['R001' => 10.5]
     */
    public static function evaluar(?string $formula, array $valoresPorCodigoLinea): ?float
    {
        $formula = strtoupper(trim((string) $formula));
        if ($formula === '') {
            return null;
        }

        // Solo letras R, dígitos, +, -, *, /, espacios y paréntesis
        if (! preg_match('/^[R0-9+\-*\/().\s]+$/', $formula)) {
            return null;
        }

        $expr = preg_replace_callback('/R\d+/', function (array $m) use ($valoresPorCodigoLinea) {
            $key = $m[0];
            $val = $valoresPorCodigoLinea[$key] ?? 0.0;

            return '('.var_export((float) $val, true).')';
        }, $formula);

        if ($expr === null || $expr === '') {
            return null;
        }

        try {
            // phpcs:ignore
            $result = eval('return (float) ('.$expr.');');
        } catch (\Throwable) {
            return null;
        }

        return is_numeric($result) ? round((float) $result, 2) : null;
    }
}
