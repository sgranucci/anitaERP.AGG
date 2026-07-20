<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Formato numérico unificado para las exportaciones Excel/CSV del sistema.
 *
 * Modos:
 *  - auto: el xlsx guarda NÚMEROS reales con máscara neutra (#,##0.00). Excel/LibreOffice
 *          los muestra según la configuración regional de la PC que abre el archivo
 *          (1.234,56 en Argentina, 1,234.56 en formato internacional). Sin botón por pantalla.
 *  - ar:   fuerza 1.234,56 escribiendo TEXTO preformateado.
 *  - intl: fuerza 1,234.56 escribiendo TEXTO preformateado.
 *
 * CSV no lleva metadatos de formato: en modo "auto" cae al respaldo config('export.csv_fallback').
 */
class ExcelFormatoNumero
{
    public const AUTO = 'auto';

    public const AR = 'ar';

    public const INTL = 'intl';

    public static function normalizar(mixed $valor): string
    {
        $v = strtolower(trim((string) $valor));

        return match ($v) {
            self::INTL => self::INTL,
            self::AR => self::AR,
            self::AUTO => self::AUTO,
            default => self::AUTO,
        };
    }

    /**
     * Preferencia global del sistema (config/export.php) ya normalizada.
     */
    public static function preferenciaGlobal(): string
    {
        return self::normalizar(config('export.formato_numero', self::AUTO));
    }

    public static function esAuto(string $formato): bool
    {
        return self::normalizar($formato) === self::AUTO;
    }

    public static function esInternacional(string $formato): bool
    {
        return self::normalizar($formato) === self::INTL;
    }

    /**
     * Formato efectivo para CSV: "auto" no puede adaptarse (texto plano), cae al respaldo.
     */
    public static function paraCsv(string $formato): string
    {
        $formato = self::normalizar($formato);

        if ($formato !== self::AUTO) {
            return $formato;
        }

        $fallback = self::normalizar(config('export.csv_fallback', self::AR));

        return $fallback === self::AUTO ? self::AR : $fallback;
    }

    /**
     * Máscara de formato PhpSpreadsheet para celdas con NÚMERO real.
     *
     * En un xlsx, la coma (miles) y el punto (decimales) son marcadores de posición:
     * Excel los reemplaza por los separadores de la config regional de la PC que abre.
     */
    public static function mascara(int $decimales = 2): string
    {
        if ($decimales <= 0) {
            return '#,##0';
        }

        return '#,##0.'.str_repeat('0', $decimales);
    }

    /**
     * Formatea un importe como TEXTO según el formato indicado (para modo ar/intl y CSV).
     */
    public static function formatearTexto(float $valor, string $formato, int $decimales = 2): string
    {
        if (self::esInternacional($formato)) {
            return number_format($valor, $decimales, '.', ',');
        }

        // ar y cualquier fallback no internacional
        return number_format($valor, $decimales, ',', '.');
    }

    /**
     * Callable para la vista Excel.
     *
     * - auto: devuelve el número crudo (punto decimal, sin miles) para que el lector HTML
     *         lo cargue como celda numérica; la máscara se aplica vía WithColumnFormatting.
     * - ar/intl: devuelve el texto ya formateado.
     *
     * En ambos modos, un valor 0/null/'' devuelve '' (celda en blanco), como venía el sistema.
     *
     * @return callable(mixed): string
     */
    public static function formateadorMonto(string $formato, int $decimales = 2): callable
    {
        $formato = self::normalizar($formato);

        return static function ($valor) use ($formato, $decimales): string {
            if ($valor === null || $valor === '' || (float) $valor === 0.0) {
                return '';
            }

            if ($formato === self::AUTO) {
                // Número crudo, punto decimal, sin separador de miles: el lector HTML lo castea a número.
                return number_format((float) $valor, $decimales, '.', '');
            }

            return self::formatearTexto((float) $valor, $formato, $decimales);
        };
    }

    /**
     * Código de formato para WithColumnFormatting según el modo.
     *
     * - auto: máscara numérica (adapta a la PC).
     * - ar/intl: texto (los valores ya vienen preformateados).
     */
    public static function codigoColumna(string $formato, int $decimales = 2): string
    {
        return self::esAuto($formato)
            ? self::mascara($decimales)
            : NumberFormat::FORMAT_TEXT;
    }

    public static function etiqueta(string $formato): string
    {
        return match (self::normalizar($formato)) {
            self::INTL => 'Internacional (1,234.56)',
            self::AR => 'Argentina (1.234,56)',
            default => 'Automático (según la configuración regional de cada PC)',
        };
    }
}
