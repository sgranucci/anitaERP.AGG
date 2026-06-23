<?php

namespace App\Support\Stock;

/**
 * Clave Anita stkmov/recepmae originada en anitaERP: letra X + sucursal 99+código empresa.
 */
final class AnitaStkmovClaveErpSupport
{
    public static function letra(): string
    {
        return substr((string) config('stock.anita_stkmov.letra', 'X'), 0, 1);
    }

    public static function sucursalBase(): int
    {
        return (int) config('stock.anita_stkmov.sucursal_erp', 99);
    }

    /** Sucursal virtual ERP: concat base + código empresa (1 → 991, 2 → 992, 12 → 9912). */
    public static function sucursal(?int $empresaCodigoAnita = null): int
    {
        if ($empresaCodigoAnita !== null && $empresaCodigoAnita > 0) {
            return (int) ((string) self::sucursalBase().(string) $empresaCodigoAnita);
        }

        return self::sucursalBase();
    }

    public static function cantidadStkmov(float $cantidad): float
    {
        return abs($cantidad);
    }
}
