<?php

namespace App\Support\Caja\Flash;

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
}
