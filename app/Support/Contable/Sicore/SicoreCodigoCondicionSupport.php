<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

final class SicoreCodigoCondicionSupport
{
    public static function desdeCondicionIvaCliente(?string $nombreCondicion): int
    {
        $n = strtoupper(trim((string) $nombreCondicion));

        return match (true) {
            str_contains($n, 'EXENT') => 10,
            str_contains($n, 'NO INSCRIP') => 2,
            str_contains($n, 'NO RESPONS') => 3,
            str_contains($n, 'MONOTRIB') => 3,
            str_contains($n, 'NO CATEG') => 3,
            default => 1,
        };
    }

    public static function desdeCondicionIvaProveedor(?string $codigoLegacy): int
    {
        $c = strtoupper(trim((string) $codigoLegacy));

        return match ($c) {
            'N', '2' => 2,
            'E', 'X' => 10,
            'M', '3' => 3,
            default => 1,
        };
    }
}
