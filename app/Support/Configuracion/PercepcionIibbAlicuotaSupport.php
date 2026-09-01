<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\Provincia;

/**
 * Ajustes de alícuota IIBB después de padrón/descarte.
 *
 * Córdoba: tope configurable en provincia.tope_alicuota_percepcion.
 * Tucumán: el archivo de tasas no trae tasapercepcion; la alícuota está en
 * coeficiente. Si excluido=E, tasa × coeficiente (0 si no hay coef).
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
     * Alícuota del padrón de Tucumán cuando tasapercepcion viene vacío.
     * El valor vive en coeficiente (1.5, 2.5, 3.5, 5…).
     *
     * @param  array<string, mixed>|object|null  $registroPadron
     */
    public static function alicuotaDesdeCoeficienteTucuman(int $jurisdiccion, $registroPadron): ?float
    {
        if ($jurisdiccion !== self::JURISDICCION_TUCUMAN || $registroPadron === null) {
            return null;
        }

        $coef = self::coeficientePadron($registroPadron);

        return $coef;
    }

    /**
     * @param  array<string, mixed>|object|null  $registroPadron
     */
    public static function aplicarPoliticaTucumanAnita(int $jurisdiccion, $registroPadron, float $tasa): float
    {
        if ($jurisdiccion !== self::JURISDICCION_TUCUMAN || $registroPadron === null) {
            return $tasa;
        }

        $excluido = strtoupper(trim((string) self::campoPadron($registroPadron, 'excluido', '')));
        if ($excluido !== self::EXCLUIDO_TUCUMAN) {
            return $tasa;
        }

        $coef = self::coeficientePadron($registroPadron);

        return $tasa * ($coef ?? 0.0);
    }

    /**
     * @param  array<string, mixed>|object  $registroPadron
     */
    private static function coeficientePadron($registroPadron): ?float
    {
        $coefRaw = self::campoPadron($registroPadron, 'coeficiente', null);
        if ($coefRaw === null || $coefRaw === '') {
            return null;
        }

        return (float) $coefRaw;
    }

    /**
     * @param  array<string, mixed>|object  $registro
     */
    private static function campoPadron($registro, string $clave, mixed $default): mixed
    {
        if (is_array($registro)) {
            return $registro[$clave] ?? $default;
        }

        return $registro->{$clave} ?? $default;
    }
}
