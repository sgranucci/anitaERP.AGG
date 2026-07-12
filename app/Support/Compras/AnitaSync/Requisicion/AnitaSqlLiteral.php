<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

use App\Support\Anita\AnitaTextoSanitizer;

final class AnitaSqlLiteral
{
    public static function string(?string $value, int $maxLen): string
    {
        $trim = AnitaTextoSanitizer::sanitizar($value);
        if ($maxLen > 0 && mb_strlen($trim) > $maxLen) {
            $trim = mb_substr($trim, 0, $maxLen);
        }

        return "'".str_replace("'", "''", $trim)."'";
    }

    public static function char(?string $value, int $maxLen = 1): string
    {
        $trim = trim((string) $value);
        if ($trim === '') {
            return "' '";
        }

        return self::string(mb_substr($trim, 0, $maxLen), $maxLen);
    }

    public static function int(?int $value): string
    {
        return (string) (int) ($value ?? 0);
    }

    public static function decimal(?float $value, int $decimals = 4): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }
}
