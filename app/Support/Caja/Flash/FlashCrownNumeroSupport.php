<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

/**
 * Números del Flash Crown en pantalla: miles visibles (AR) + valor crudo usable (data-valor).
 * Excel: celdas TYPE_NUMERIC + máscara #,##0.00 (ver exports FlashCrown*).
 */
final class FlashCrownNumeroSupport
{
    /**
     * HTML listo para celda/KPI: muestra 1.234.567,89 y data-valor="1234567.89".
     */
    public static function html(mixed $valor, int $decimales = 2, bool $negrita = false): string
    {
        if ($valor === null || $valor === '') {
            $valor = 0;
        }

        $num = (float) $valor;
        if ($decimales <= 0) {
            $num = (float) (int) round($num);
            $texto = number_format($num, 0, ',', '.');
            $crudo = (string) (int) $num;
        } else {
            $texto = number_format($num, $decimales, ',', '.');
            $crudo = number_format($num, $decimales, '.', '');
        }

        $cls = 'fc-num'.($negrita ? ' font-weight-bold' : '');

        return '<span class="'.$cls.'" data-valor="'.e($crudo).'">'.e($texto).'</span>';
    }

    /**
     * Solo el texto con miles (sin HTML), p. ej. inputs / títulos.
     */
    public static function texto(mixed $valor, int $decimales = 2): string
    {
        if ($valor === null || $valor === '') {
            $valor = 0;
        }

        return number_format((float) $valor, max(0, $decimales), ',', '.');
    }

    /**
     * Interpreta texto AR (1.234.567,89), intl (1234567.89) o miles con puntos.
     */
    public static function parsear(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $s = trim((string) $raw);
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        if ($s === '' || $s === '-' || $s === ',' || $s === '.') {
            return 0.0;
        }

        $neg = false;
        if (str_starts_with($s, '-')) {
            $neg = true;
            $s = substr($s, 1);
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }

        $num = (float) $s;

        return $neg ? -$num : $num;
    }

    public static function parsearEntero(mixed $raw): int
    {
        return (int) round(self::parsear($raw));
    }
}
