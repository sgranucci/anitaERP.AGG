<?php

namespace App\Support\Contable;

use App\Support\Export\ExcelFormatoNumero;

/**
 * Formato numérico del Excel/CSV de mayor por concepto.
 *
 * Delega en {@see ExcelFormatoNumero} (helper global). Se mantiene por compatibilidad
 * con las llamadas existentes de este reporte.
 *
 * - auto: xlsx con números reales; cada PC lo muestra según su config regional (default)
 * - ar:   1.234,56 (Argentina, texto)
 * - intl: 1,234.56 (internacional, texto)
 */
class MayorConceptoExcelFormatoNumero
{
    public const AUTO = ExcelFormatoNumero::AUTO;

    public const AR = ExcelFormatoNumero::AR;

    public const INTL = ExcelFormatoNumero::INTL;

    public static function normalizar(mixed $valor): string
    {
        return ExcelFormatoNumero::normalizar($valor);
    }

    public static function esAuto(string $formato): bool
    {
        return ExcelFormatoNumero::esAuto($formato);
    }

    public static function esInternacional(string $formato): bool
    {
        return ExcelFormatoNumero::esInternacional($formato);
    }

    /**
     * @return callable(mixed): string
     */
    public static function formateadorMonto(string $formato, int $decimales = 2): callable
    {
        return ExcelFormatoNumero::formateadorMonto($formato, $decimales);
    }

    /**
     * Formatea a texto legible. En modo "auto" (que en xlsx va como número real) se usa
     * el formato Argentina para los textos inline (subtítulos, porcentajes de conciliación).
     */
    public static function formatear(float $valor, string $formato, int $decimales = 2): string
    {
        $formato = ExcelFormatoNumero::normalizar($formato);

        if ($formato === ExcelFormatoNumero::AUTO) {
            $formato = ExcelFormatoNumero::AR;
        }

        return ExcelFormatoNumero::formatearTexto($valor, $formato, $decimales);
    }

    /**
     * Código de formato de columna para WithColumnFormatting.
     */
    public static function codigoColumna(string $formato, int $decimales = 2): string
    {
        return ExcelFormatoNumero::codigoColumna($formato, $decimales);
    }

    public static function etiqueta(string $formato): string
    {
        return ExcelFormatoNumero::etiqueta($formato);
    }
}
