<?php

declare(strict_types=1);

namespace App\Support\Configuracion\Sircip;

/**
 * Campo 7 del padrón SIRCIP (25 dígitos).
 *
 * Se lee de derecha a izquierda: el dígito más a la derecha es el 0 fijo y
 * después vienen las jurisdicciones 901…924. Para la jurisdicción J el índice
 * (contando desde la izquierda, base 0) es 924 - J.
 *
 * Códigos por jurisdicción (guía operativa SIRCIP):
 *   1 = adherida, dado de alta, no aplica sobretasa
 *   2 = adherida, no dado de alta, aplica sobretasa
 *   3 = adherida, no dado de alta, no aplica sobretasa
 *   4 = no adherida, dado de alta
 *   5 = no adherida, no dado de alta
 */
final class SircipCampo7
{
    public const LARGO = 25;

    public const JURISDICCION_MIN = 901;

    public const JURISDICCION_MAX = 924;

    public static function estado(string $campo7, int $jurisdiccion): ?int
    {
        if ($jurisdiccion < self::JURISDICCION_MIN || $jurisdiccion > self::JURISDICCION_MAX) {
            return null;
        }
        if (strlen($campo7) !== self::LARGO || ! ctype_digit($campo7)) {
            return null;
        }

        return (int) $campo7[self::JURISDICCION_MAX - $jurisdiccion];
    }

    /** La jurisdicción está adherida al SIRCIP (códigos 1, 2 o 3). */
    public static function adherida(string $campo7, int $jurisdiccion): bool
    {
        $estado = self::estado($campo7, $jurisdiccion);

        return $estado !== null && $estado <= 3;
    }

    /** Dado de alta en la jurisdicción (códigos 1 y 4). */
    public static function dadoDeAlta(string $campo7, int $jurisdiccion): bool
    {
        $estado = self::estado($campo7, $jurisdiccion);

        return $estado === 1 || $estado === 4;
    }

    /**
     * Jurisdicciones adheridas según el propio campo 7 (no hay lista fija).
     *
     * @return list<int>
     */
    public static function jurisdiccionesAdheridas(string $campo7): array
    {
        $out = [];
        for ($j = self::JURISDICCION_MIN; $j <= self::JURISDICCION_MAX; $j++) {
            if (self::adherida($campo7, $j)) {
                $out[] = $j;
            }
        }

        return $out;
    }
}
