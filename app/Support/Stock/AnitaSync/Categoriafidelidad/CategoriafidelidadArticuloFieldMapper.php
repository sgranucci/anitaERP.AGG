<?php

namespace App\Support\Stock\AnitaSync\Categoriafidelidad;

use App\Models\Stock\Articulo;

/**
 * Mapeo campo a campo: clicatart (Anita) → categoriafidelidad_articulo_gastronomia (ERP).
 */
final class CategoriafidelidadArticuloFieldMapper
{
    public static function mapCodigoCategoria(object $row): ?string
    {
        $codigo = (int) ($row->clcart_categoria ?? 0);

        return $codigo > 0 ? (string) $codigo : null;
    }

    public static function mapSkuAnita(object $row): string
    {
        return trim((string) ($row->clcart_articulo ?? ''));
    }

    public static function mapOrden(object $row): int
    {
        return (int) ($row->clcart_orden ?? 0);
    }

    /**
     * Resuelve el id del artículo ERP a partir del SKU Informix (clcart_articulo char(13)).
     */
    public static function resolverArticuloId(string $skuAnita): ?int
    {
        $skuRaw = trim($skuAnita);
        if ($skuRaw === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            ltrim($skuRaw, '0'),
            $skuRaw,
        ], static fn ($v) => $v !== '')));

        foreach ($candidatos as $sku) {
            $articulo = Articulo::query()->where('sku', $sku)->first();
            if ($articulo) {
                return (int) $articulo->id;
            }
        }

        return null;
    }
}
