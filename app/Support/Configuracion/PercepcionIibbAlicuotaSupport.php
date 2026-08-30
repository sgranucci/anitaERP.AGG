<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Provincia;

/**
 * Ajustes de alícuota IIBB después de padrón/descarte.
 *
 * Córdoba (Anita): tope configurable en provincia.tope_alicuota_percepcion.
 * Tucumán (Anita calc_ing_bruto): si excluido=E, tasa × coeficiente (0 si no hay coef).
 */
final class PercepcionIibbAlicuotaSupport
{
    public const JURISDICCION_TUCUMAN = 924;

    public const EXCLUIDO_TUCUMAN = 'E';

    public static function aplicarTope(float $tasa, ?Provincia $provincia): float
    {
        if ($provincia === null) {
            return $tasa;
        }
        $tope = $provincia->tope_alicuota_percepcion;
        if ($tope === null || $tope === '') {
            return $tasa;
        }
        $tope = (float) $tope;
        if ($tope <= 0.00001) {
            return $tasa;
        }

        return $tasa > $tope ? $tope : $tasa;
    }

    /**
     * @param  array<string, mixed>|object|null  $registroPadron
     */
    public static function aplicarPoliticaTucumanAnita(int $jurisdiccion, $registroPadron, float $tasa): float
    {
        if ($jurisdiccion !== self::JURISDICCION_TUCUMAN || $registroPadron === null) {
            return $tasa;
        }

        $excluido = strtoupper(trim((string) (is_array($registroPadron)
            ? ($registroPadron['excluido'] ?? '')
            : ($registroPadron->excluido ?? ''))));
        if ($excluido !== self::EXCLUIDO_TUCUMAN) {
            return $tasa;
        }

        $coefRaw = is_array($registroPadron)
            ? ($registroPadron['coeficiente'] ?? null)
            : ($registroPadron->coeficiente ?? null);
        $coef = ($coefRaw === null || $coefRaw === '') ? 0.0 : (float) $coefRaw;

        return $tasa * $coef;
    }
}
