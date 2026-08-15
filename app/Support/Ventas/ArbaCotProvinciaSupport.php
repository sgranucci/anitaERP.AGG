<?php

namespace App\Support\Ventas;

/**
 * Códigos de provincia del archivo COT ARBA (1 letra).
 * El ERP usa abreviaturas Anita (BAI = Buenos Aires).
 */
final class ArbaCotProvinciaSupport
{
    private const MAPA = [
        'B' => 'B',
        'BA' => 'B',
        'BAI' => 'B',
        'BSAS' => 'B',
        'PBA' => 'B',
        'BUENOS AIRES' => 'B',
        'C' => 'C',
        'CABA' => 'C',
        'CF' => 'C',
        'CAPITAL' => 'C',
        'CAPITAL FEDERAL' => 'C',
        'A' => 'A',
        'D' => 'D',
        'E' => 'E',
        'F' => 'F',
        'G' => 'G',
        'H' => 'H',
        'J' => 'J',
        'K' => 'K',
        'L' => 'L',
        'M' => 'M',
        'N' => 'N',
        'P' => 'P',
        'Q' => 'Q',
        'R' => 'R',
        'S' => 'S',
        'T' => 'T',
        'U' => 'U',
        'V' => 'V',
        'W' => 'W',
        'X' => 'X',
        'Y' => 'Y',
        'Z' => 'Z',
    ];

    public static function codigo(?string $abreviatura): string
    {
        $valor = strtoupper(trim((string) $abreviatura));
        if ($valor === '') {
            return 'B';
        }

        return self::MAPA[$valor] ?? 'B';
    }
}
