<?php

namespace App\Support\Numerico;

/**
 * Convierte importes/cotizaciones de formularios (AR o EN) a float antes de grabar.
 *
 * Acepta 1.234.567,89 / 1.234.567 / 1234,56 (AR) y 1,234,567.89 / 1234.56 (EN).
 * No usar (float) ni abs() directo sobre el string del POST: (float)"1.000,50" → 1.0
 * y abs("1.000,50") TypeError en PHP 8+.
 */
final class NumeroDecimalLocalSupport
{
    public static function aFloat(mixed $raw, float $default = 0.0): float
    {
        return self::aFloatONull($raw) ?? $default;
    }

    public static function aFloatONull(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        if (is_bool($raw)) {
            return $raw ? 1.0 : 0.0;
        }

        $texto = trim((string) $raw);
        if ($texto === '') {
            return null;
        }

        $negativo = false;
        if (str_starts_with($texto, '-')) {
            $negativo = true;
            $texto = ltrim(substr($texto, 1));
        } elseif (str_starts_with($texto, '+')) {
            $texto = ltrim(substr($texto, 1));
        }

        $texto = str_replace([' ', "\xc2\xa0"], '', $texto);
        $texto = preg_replace('/^\$+\s*/u', '', $texto) ?? $texto;

        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return null;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                // 1.208.932,47
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                // 1,208,932.47
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $partes = explode(',', $texto);
            if (count($partes) === 2 && strlen($partes[1]) <= 6) {
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimoPunto !== false) {
            // Solo puntos: 1.571.612.21 (miles+dec) vs 253.875.82 vs 55.000.000 (solo miles) vs 72535.95 (EN).
            $partes = explode('.', $texto);
            $ultima = $partes[array_key_last($partes)];
            $nPartes = count($partes);
            if ($nPartes >= 3 && strlen($ultima) === 3) {
                // Solo miles AR: 55.000.000 / 1.208.932
                $texto = str_replace('.', '', $texto);
            } elseif ($nPartes >= 3 && strlen($ultima) >= 1 && strlen($ultima) <= 6) {
                // Miles + decimales: 1.571.612.21
                $dec = array_pop($partes);
                $texto = implode('', $partes).'.'.$dec;
            } elseif ($nPartes === 2 && strlen($ultima) === 3) {
                // Un grupo de miles: 55.000
                $texto = str_replace('.', '', $texto);
            }
            // else: un punto con 1–2 (o >3) decimales → ya es float EN (1234.56)
        }

        $texto = preg_replace('/[^\d.]/', '', $texto) ?? '';
        if ($texto === '' || ! is_numeric($texto)) {
            return null;
        }

        $valor = (float) $texto;

        return $negativo ? -$valor : $valor;
    }

    /**
     * @param  array<int|string, mixed>  $valores
     * @return array<int, float>
     */
    public static function listaAFloat(array $valores, float $default = 0.0): array
    {
        $out = [];
        foreach ($valores as $valor) {
            $out[] = self::aFloat($valor, $default);
        }

        return $out;
    }
}
