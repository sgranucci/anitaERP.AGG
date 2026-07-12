<?php

namespace App\Support\Anita;

/**
 * Sanitiza texto ERP (UTF-8) antes de escribirlo en Anita/Informix.
 *
 * Informix opera con un codepage de un solo byte y rechaza con el error
 * "-202: An illegal character has been found in the statement" cualquier byte
 * multibyte UTF-8 (acentos, Ñ, comillas tipográficas, guiones largos, etc.) o
 * carácter de control embebido en la sentencia SQL.
 *
 * Estrategia:
 *  1. Normaliza saltos de línea y tabs a espacio (evita SQL en varias líneas).
 *  2. Translitera acentos y diacríticos a su equivalente ASCII (á→a, Ñ→N, …→...).
 *  3. Red de seguridad: elimina cualquier byte fuera de ASCII imprimible (0x20–0x7E).
 *  4. Colapsa espacios múltiples.
 *
 * El paso 3 garantiza que jamás llegue un carácter ilegal a Informix, aun si la
 * transliteración falla por locale.
 */
final class AnitaTextoSanitizer
{
    /** Reemplazos determinísticos previos al iconv (independientes del locale). */
    private const REEMPLAZOS = [
        '“' => '"', '”' => '"', '„' => '"', '‟' => '"',
        '‘' => "'", '’' => "'", '‚' => "'", '‛' => "'",
        '–' => '-', '—' => '-', '−' => '-',
        '…' => '...',
        '¿' => '', '¡' => '',
        '€' => 'EUR', '°' => '', 'º' => 'o', 'ª' => 'a',
        "\xC2\xA0" => ' ', // NBSP
    ];

    public static function sanitizar(?string $texto): string
    {
        $s = (string) $texto;
        if ($s === '') {
            return '';
        }

        $s = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $s);
        $s = strtr($s, self::REEMPLAZOS);

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($translit !== false) {
            $s = $translit;
        }

        $s = preg_replace('/[^\x20-\x7E]/', '', $s) ?? '';
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }
}
