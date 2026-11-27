<?php

declare(strict_types=1);

namespace App\Support\Configuracion\Sircip;

/**
 * Lee una línea del padrón SIRCIP mensual (CSV UTF-8, 7 campos):
 *   periodo,cuit,razon_social,jurisdiccion_sede,crc,alicuota_tabulada_letra,campo7
 *
 * Devuelve null si la línea es cabecera, está vacía o no cumple el diseño.
 */
final class SircipPadronLineaParser
{
    /** Letras válidas de alícuota (A = 0 % … X = 5 %). */
    public const LETRAS = 'ABCDEFGHIJKLMNOPQRSTUVWX';

    /**
     * @return array{periodo:string,cuit:string,razon_social:?string,jurisdiccion_sede:?int,crc:int,alicuota_letra:string,campo7:string}|null
     */
    public static function parsear(string $linea): ?array
    {
        $linea = rtrim($linea, "\r\n");
        if (trim($linea) === '') {
            return null;
        }

        $c = str_getcsv($linea, ',', '"', '\\');
        if (count($c) !== 7) {
            return null;
        }

        $periodo = trim($c[0]);
        if ($periodo === 'periodo' || ! preg_match('/^\d{6}$/', $periodo)) {
            return null;
        }

        $cuit = preg_replace('/\D/', '', $c[1]) ?? '';
        if (strlen($cuit) !== 11) {
            return null;
        }

        $crc = trim($c[4]);
        if (! ctype_digit($crc) || (int) $crc < 10 || (int) $crc > 99) {
            return null;
        }

        $letra = strtoupper(trim($c[5]));
        if (strlen($letra) !== 1 || ! str_contains(self::LETRAS, $letra)) {
            return null;
        }

        $campo7 = trim($c[6]);
        if (strlen($campo7) !== SircipCampo7::LARGO || ! ctype_digit($campo7)) {
            return null;
        }

        $sede = trim($c[3]);

        return [
            'periodo' => $periodo,
            'cuit' => $cuit,
            'razon_social' => mb_substr(trim($c[2]), 0, 255) ?: null,
            'jurisdiccion_sede' => ctype_digit($sede) ? (int) $sede : null,
            'crc' => (int) $crc,
            'alicuota_letra' => $letra,
            'campo7' => $campo7,
        ];
    }

    /** Porcentaje de la letra (A = 0 … X = 5). Null si la letra no existe. */
    public static function porcentajeLetra(string $letra): ?float
    {
        $letra = strtoupper($letra);
        $tabla = [
            'A' => 0.00, 'B' => 0.01, 'C' => 0.05, 'D' => 0.10, 'E' => 0.20, 'F' => 0.30,
            'G' => 0.40, 'H' => 0.50, 'I' => 0.60, 'J' => 0.70, 'K' => 0.80, 'L' => 1.00,
            'M' => 1.20, 'N' => 1.40, 'O' => 1.50, 'P' => 1.60, 'Q' => 1.80, 'R' => 2.00,
            'S' => 2.50, 'T' => 3.00, 'U' => 3.50, 'V' => 4.00, 'W' => 4.50, 'X' => 5.00,
        ];

        return $tabla[$letra] ?? null;
    }
}
