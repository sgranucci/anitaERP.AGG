<?php

namespace App\Support\Caja\Flash;

use App\Support\Export\ExcelFormatoNumero;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Helpers de formato para el Consolidated Income (l-flash).
 */
final class FlashCajaLFlashFormatoSupport
{
    public static function n($valor, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $dec, ',', '.');
    }

    public static function nExcel($valor, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $dec, '.', '');
    }

    public static function entero($valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return (string) (int) round((float) $valor);
    }

    public static function pct($valor, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $dec, ',', '.').'%';
    }

    public static function pctExcel($valor, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $dec, '.', '').'%';
    }

    /**
     * Número para celdas Excel según EXPORT_FORMATO_NUMERO (auto/ar/intl).
     * En auto escribe número crudo (punto decimal) para que la máscara regional de la PC aplique.
     * Incluye ceros (el flash los muestra en pantalla).
     */
    public static function nExcelFormato($valor, string $formato, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $formato = ExcelFormatoNumero::normalizar($formato);
        if (ExcelFormatoNumero::esAuto($formato)) {
            return number_format((float) $valor, $dec, '.', '');
        }

        return ExcelFormatoNumero::formatearTexto((float) $valor, $formato, $dec);
    }

    /**
     * Porcentaje con sufijo % (texto). En auto/ar/intl adapta separadores del número.
     */
    public static function pctExcelFormato($valor, string $formato, int $dec = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return self::nExcelFormato($valor, $formato, $dec).'%';
    }

    /**
     * Entero para Excel según formato regional (auto = número crudo).
     */
    public static function enteroExcelFormato($valor, string $formato): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        $entero = (int) round((float) $valor);
        $formato = ExcelFormatoNumero::normalizar($formato);
        if (ExcelFormatoNumero::esAuto($formato)) {
            return (string) $entero;
        }

        return ExcelFormatoNumero::formatearTexto((float) $entero, $formato, 0);
    }

    /**
     * Máscaras de columna A–AZ del Consolidated Income (52 cols).
     *
     * @return array<string, string>
     */
    public static function columnFormatsExcel(string $formato): array
    {
        $formato = ExcelFormatoNumero::normalizar($formato);
        $texto = NumberFormat::FORMAT_TEXT;
        $int = ExcelFormatoNumero::codigoColumna($formato, 0);
        $dec1 = ExcelFormatoNumero::codigoColumna($formato, 1);
        $dec2 = ExcelFormatoNumero::codigoColumna($formato, 2);

        // A Day, B Fecha — texto
        // C Custom, D Units, L Seats, U Pos, Y Cards, AK Pos OL, AM Cust Budg, AY/AZ Vehicles — enteros
        // H/I/J, P/Q/R, AB, AE, AG, AJ — 1 decimal (ratios / % sin sufijo)
        // AN–AX — % con sufijo (texto)
        // resto importes — 2 decimales
        $fmt = [
            'A' => $texto,
            'B' => $texto,
            'C' => $int,
            'D' => $int,
            'E' => $dec2,
            'F' => $dec2,
            'G' => $dec2,
            'H' => $dec1,
            'I' => $dec1,
            'J' => $dec1,
            'K' => $int,
            'L' => $int,
            'M' => $dec2,
            'N' => $dec2,
            'O' => $dec2,
            'P' => $dec1,
            'Q' => $dec1,
            'R' => $dec1,
            'S' => $int,
            'T' => $int,
            'U' => $int,
            'V' => $dec2,
            'W' => $dec2,
            'X' => $dec2,
            'Y' => $int,
            'Z' => $dec2,
            'AA' => $dec2,
            'AB' => $dec1,
            'AC' => $dec2,
            'AD' => $dec2,
            'AE' => $dec1,
            'AF' => $dec2,
            'AG' => $dec1,
            'AH' => $dec2,
            'AI' => $dec2,
            'AJ' => $dec1,
            'AK' => $int,
            'AL' => $int,
            'AM' => $int,
            'AN' => $texto,
            'AO' => $texto,
            'AP' => $texto,
            'AQ' => $texto,
            'AR' => $texto,
            'AS' => $texto,
            'AT' => $texto,
            'AU' => $texto,
            'AV' => $texto,
            'AW' => $texto,
            'AX' => $texto,
            'AY' => $int,
            'AZ' => $int,
        ];

        return $fmt;
    }
}
