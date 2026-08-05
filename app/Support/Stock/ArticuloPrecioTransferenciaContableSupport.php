<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Precio unitario para el asiento contable de transferencias / mov. stock.
 *
 * - fl_precio_promedio_transferencia (TITO): promedio 3 compras ERP; fallback stkmae compra1/2/3
 * - resto: última compra ERP → Anita stkm_pre_compra3 → costo/PPP artículo
 */
final class ArticuloPrecioTransferenciaContableSupport
{
    public static function usaPrecioPromedio(Articulo $articulo): bool
    {
        return (bool) ($articulo->fl_precio_promedio_transferencia ?? false);
    }

    public static function resolverPrecioUnitario(Articulo $articulo): ?float
    {
        $sku = trim((string) ($articulo->sku ?? ''));
        if ($sku === '') {
            return null;
        }

        if (self::usaPrecioPromedio($articulo)) {
            return ArticuloPrecioPromedioCompraSupport::resolverPrecioUnitario($articulo);
        }

        $dato = ArticuloPrecioUltimaCompraSupport::resolverPorArticulo($articulo);
        $precio = $dato['precio'] ?? null;

        return $precio !== null && (float) $precio > 0 ? round((float) $precio, 6) : null;
    }

    /**
     * @param  iterable<Articulo>  $articulos
     * @return array<int, float|null> articulo.id => precio
     */
    public static function resolverPreciosPorArticulos(iterable $articulos): array
    {
        $titos = [];
        $resto = [];

        foreach ($articulos as $articulo) {
            if (! $articulo instanceof Articulo) {
                continue;
            }
            $id = (int) $articulo->id;
            $sku = trim((string) ($articulo->sku ?? ''));
            if ($id <= 0 || $sku === '') {
                continue;
            }
            if (self::usaPrecioPromedio($articulo)) {
                $titos[$id] = $articulo;
            } else {
                $resto[$id] = $articulo;
            }
        }

        $porId = [];

        if ($titos !== []) {
            foreach (ArticuloPrecioPromedioCompraSupport::resolverPorArticulos($titos) as $id => $dato) {
                $precio = $dato['precio'] ?? null;
                $porId[$id] = $precio !== null && (float) $precio > 0
                    ? round((float) $precio, 6)
                    : null;
            }
        }

        if ($resto !== []) {
            foreach (ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($resto) as $id => $dato) {
                $precio = $dato['precio'] ?? null;
                $porId[$id] = $precio !== null && (float) $precio > 0
                    ? round((float) $precio, 6)
                    : null;
            }
        }

        return $porId;
    }
}
