<?php

namespace App\Support\Contable;

/**
 * Parseo de montos en formato es-AR (1.234,56) o en-US (1,234.56 / 1234.56).
 * Alineado con public/.../asiento/montos_formato.js.
 */
final class MontoEsArSupport
{
    public static function parse(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        if (is_int($raw) || is_float($raw)) {
            return round((float) $raw, 2);
        }

        $t = trim((string) $raw);
        $t = str_replace(["\xc2\xa0", ' '], '', $t);
        if ($t === '') {
            return 0.0;
        }

        // es-AR: 1.234,56
        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
            // Miles sin decimales: 1.234.567
            $t = str_replace('.', '', $t);
        }

        if (! is_numeric($t)) {
            return 0.0;
        }

        return round((float) $t, 2);
    }
}
