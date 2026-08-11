<?php

namespace App\Support\Stock\Surmar;

/**
 * Permisos del menú Movimientos Surmar vs ABM general de movimientos de stock.
 * Roles solo-Surmar (ej. Enc-produccion-surmar) no tienen listar/crear-movimientos-de-stock.
 */
final class MovimientoSurmarPermisoSupport
{
    public static function puedeListar(bool $redirect = true): bool
    {
        return self::alguno(['listar-movimientos-de-stock', 'listar-movimiento-surmar'], $redirect);
    }

    public static function puedeCrear(bool $redirect = true): bool
    {
        return self::alguno(['crear-movimientos-de-stock', 'crear-movimiento-surmar'], $redirect);
    }

    public static function puedeEditar(bool $redirect = true): bool
    {
        return self::alguno(['editar-movimientos-de-stock', 'editar-movimiento-surmar'], $redirect);
    }

    public static function puedeActualizar(bool $redirect = true): bool
    {
        return self::alguno(['actualizar-movimientos-de-stock', 'actualizar-movimiento-surmar'], $redirect);
    }

    public static function puedeAnular(bool $redirect = true): bool
    {
        return self::alguno(['borrar-movimientos-de-stock', 'anular-movimiento-surmar'], $redirect);
    }

    public static function puedeRevertir(bool $redirect = true): bool
    {
        return self::alguno(['revertir-movimientos-de-stock', 'anular-movimiento-surmar'], $redirect);
    }

    public static function puedeImprimirEtiqueta(bool $redirect = true): bool
    {
        return self::alguno([
            'crear-movimientos-de-stock',
            'editar-movimientos-de-stock',
            'imprimir-etiqueta-movimiento-surmar',
            'listar-trazabilidad-surmar',
        ], $redirect);
    }

    public static function soloModoSurmar(): bool
    {
        return can('listar-movimiento-surmar', false)
            && ! can('listar-movimientos-de-stock', false);
    }

    /**
     * @param  list<string>  $slugs
     */
    private static function alguno(array $slugs, bool $redirect): bool
    {
        foreach ($slugs as $slug) {
            if (can($slug, false)) {
                return true;
            }
        }

        if ($redirect) {
            can($slugs[0]);
        }

        return false;
    }
}
