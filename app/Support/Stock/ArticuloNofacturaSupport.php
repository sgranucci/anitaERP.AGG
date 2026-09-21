<?php

namespace App\Support\Stock;

/**
 * Flag facturable del artículo (ERP 0/1) vs valores legacy Anita (N, I).
 *
 * Anita `stkm_fl_no_factura`: 0 = facturable; 1/N = no facturable; I = inactivo + no facturable.
 * En ERP solo se persisten '0' y '1'.
 */
final class ArticuloNofacturaSupport
{
    public const FACTURABLE = '0';

    public const NO_FACTURABLE = '1';

    /**
     * Normaliza valor Anita o ERP a '0' | '1'.
     */
    public static function normalizar(mixed $valor): string
    {
        $v = strtoupper(trim((string) ($valor ?? '')));

        return match ($v) {
            self::FACTURABLE, '' => self::FACTURABLE,
            self::NO_FACTURABLE, 'N', 'I' => self::NO_FACTURABLE,
            default => self::NO_FACTURABLE,
        };
    }

    public static function esFacturable(mixed $valor): bool
    {
        return self::normalizar($valor) === self::FACTURABLE;
    }

    public static function etiqueta(mixed $valor): string
    {
        return self::esFacturable($valor) ? 'Facturable' : 'No facturable';
    }
}
