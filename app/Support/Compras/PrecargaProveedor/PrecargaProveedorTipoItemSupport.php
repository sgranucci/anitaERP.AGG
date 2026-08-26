<?php

namespace App\Support\Compras\PrecargaProveedor;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Servicio;
use App\Models\Stock\Articulo;
use Throwable;

/**
 * Resuelve el tipo de ítem (B/S/L/U) para abreviatura fina FIS/FNS/FIB/FNB/FNU…
 *
 * Si el proveedor tiene filas en proveedor_servicio (medidores Anita), fuerza Servicio (S).
 * Indumentaria (tipo IND o categoría *INDUMENTARIA*) no es bien de uso: va como Bienes (B),
 * aunque Anita/maestro tengan stkm_tipo_articulo = U.
 */
final class PrecargaProveedorTipoItemSupport
{
    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     */
    public static function resolver(iterable $itemsOrdenCompra, ?string $cuitProveedor = null, ?int $proveedorId = null): string
    {
        if (self::proveedorTieneServicios($cuitProveedor, $proveedorId)) {
            return 'S';
        }

        return self::resolverDesdeItemsOc($itemsOrdenCompra);
    }

    /**
     * @param  iterable<int, object>  $itemsOrdenCompra
     */
    public static function resolverDesdeItemsOc(iterable $itemsOrdenCompra): string
    {
        $items = is_array($itemsOrdenCompra) ? $itemsOrdenCompra : iterator_to_array($itemsOrdenCompra);
        $indumentariaPorSku = self::mapaIndumentariaPorSku($items);

        $tipoItem = 'B';
        foreach ($items as $item) {
            if (($item->stkm_tipo_articulo ?? null) == 'S') {
                $tipoItem = 'S';
            }
            if (($item->stkm_agrupacion ?? null) == '0081') {
                $tipoItem = 'L';
            }
            if (($item->stkm_tipo_articulo ?? null) == 'U' && ! self::itemEsIndumentaria($item, $indumentariaPorSku)) {
                $tipoItem = 'U';
            }
        }

        return $tipoItem;
    }

    /**
     * Tipo IND o categoría/nombre con "INDUMENTARIA" (ropa, no activable).
     */
    public static function esIndumentariaDesdeMaestros(
        ?string $tipoAbreviatura,
        ?string $tipoNombre,
        ?string $categoriaNombre,
    ): bool {
        if (strtoupper(trim((string) $tipoAbreviatura)) === 'IND') {
            return true;
        }

        foreach ([$tipoNombre, $categoriaNombre] as $nombre) {
            $texto = mb_strtoupper(trim((string) $nombre));
            if ($texto !== '' && str_contains($texto, 'INDUMENTARIA')) {
                return true;
            }
        }

        return false;
    }

    public static function proveedorTieneServicios(?string $cuitProveedor = null, ?int $proveedorId = null): bool
    {
        if ($proveedorId !== null && $proveedorId > 0) {
            return Proveedor_Servicio::query()->where('proveedor_id', $proveedorId)->exists();
        }

        $cuit = preg_replace('/\D+/', '', (string) $cuitProveedor) ?? '';
        if ($cuit === '') {
            return false;
        }

        $proveedorIds = Proveedor::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(nroinscripcion, '-', ''), '.', ''), ' ', '') = ?",
                [$cuit]
            )
            ->pluck('id');

        if ($proveedorIds->isEmpty()) {
            return false;
        }

        return Proveedor_Servicio::query()->whereIn('proveedor_id', $proveedorIds)->exists();
    }

    /**
     * @param  array<int, object>  $items
     * @return array<string, bool>
     */
    private static function mapaIndumentariaPorSku(array $items): array
    {
        $skus = [];
        foreach ($items as $item) {
            if (property_exists($item, 'es_indumentaria')) {
                continue;
            }
            $sku = self::skuItem($item);
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        if ($skus === []) {
            return [];
        }

        try {
            $articulos = Articulo::query()
                ->with(['tipoarticulos:id,nombre,abreviatura', 'categorias:id,nombre'])
                ->whereIn('sku', array_values($skus))
                ->get(['id', 'sku', 'tipoarticulo_id', 'categoria_id']);
        } catch (Throwable) {
            return [];
        }

        $mapa = [];
        foreach ($articulos as $articulo) {
            $sku = trim((string) $articulo->sku);
            $mapa[$sku] = self::esIndumentariaDesdeMaestros(
                $articulo->tipoarticulos->abreviatura ?? null,
                $articulo->tipoarticulos->nombre ?? null,
                $articulo->categorias->nombre ?? null,
            );
        }

        return $mapa;
    }

    /**
     * @param  array<string, bool>  $indumentariaPorSku
     */
    private static function itemEsIndumentaria(object $item, array $indumentariaPorSku): bool
    {
        if (property_exists($item, 'es_indumentaria')) {
            return (bool) $item->es_indumentaria;
        }

        $sku = self::skuItem($item);
        if ($sku === '') {
            return false;
        }

        return (bool) ($indumentariaPorSku[$sku] ?? false);
    }

    private static function skuItem(object $item): string
    {
        return trim((string) ($item->penvp_articulo ?? $item->sku ?? ''));
    }
}
