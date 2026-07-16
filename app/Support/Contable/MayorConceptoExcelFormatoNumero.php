<?php

namespace App\Support\Contable;

/**
 * Formato numérico del Excel/CSV de mayor por concepto.
 *
 * - ar:   1.234,56 (Argentina)
 * - intl: 1,234.56 (internacional)
 */
class MayorConceptoExcelFormatoNumero
{
    public const AR = 'ar';

    public const INTL = 'intl';

    public static function normalizar(mixed $valor): string
    {
        $v = strtolower(trim((string) $valor));

        return $v === self::INTL ? self::INTL : self::AR;
    }

    public static function esInternacional(string $formato): bool
    {
        return self::normalizar($formato) === self::INTL;
    }

    /**
     * @return callable(mixed): string
     */
    public static function formateadorMonto(string $formato, int $decimales = 2): callable
    {
        $formato = self::normalizar($formato);

        return static function ($valor) use ($formato, $decimales): string {
            if ($valor === null || $valor === '' || (float) $valor === 0.0) {
                return '';
            }

            return self::formatear((float) $valor, $formato, $decimales);
        };
    }

    public static function formatear(float $valor, string $formato, int $decimales = 2): string
    {
        if (self::esInternacional($formato)) {
            return number_format($valor, $decimales, '.', ',');
        }

        return number_format($valor, $decimales, ',', '.');
    }

    public static function etiqueta(string $formato): string
    {
        return self::esInternacional($formato)
            ? 'Internacional (1,234.56)'
            : 'Argentina (1.234,56)';
    }
}
