<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use InvalidArgumentException;

/**
 * Exclusividad por comprobante: todas las líneas con flag color/talle,
 * o ninguna. No se mezclan.
 */
final class MovimientoStockColorTalleExclusividadSupport
{
    /**
     * @param  list<int|string|null>  $articulosId
     * @param  list<int|string|null>  $coloresId
     * @param  list<int|string|null>  $tallesId
     *
     * @throws InvalidArgumentException
     */
    public static function validarLineas(array $articulosId, array $coloresId, array $tallesId): void
    {
        $flags = [];
        $ids = [];
        foreach ($articulosId as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0) {
                continue;
            }
            $ids[] = $articuloId;
            $flags[$i] = null;
        }

        if ($ids === []) {
            return;
        }

        $articulos = Articulo::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get(['id', 'sku', 'descripcion', 'maneja_stock_color_talle'])
            ->keyBy('id');

        $modo = null; // true = color/talle, false = normal
        foreach ($articulosId as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0) {
                continue;
            }

            $art = $articulos->get($articuloId);
            if (! $art) {
                continue;
            }

            $maneja = (bool) ($art->maneja_stock_color_talle ?? false);
            if ($modo === null) {
                $modo = $maneja;
            } elseif ($modo !== $maneja) {
                throw new InvalidArgumentException(
                    'En un mismo comprobante no se pueden mezclar artículos con y sin stock por color/talle.'
                    .' Conflicto en '.$art->sku.' — '.$art->descripcion.'.'
                );
            }

            $colorId = isset($coloresId[$i]) ? (int) $coloresId[$i] : 0;
            $talleId = isset($tallesId[$i]) ? (int) $tallesId[$i] : 0;

            ArticuloStockColorTalleSupport::validarLinea($art, $colorId > 0 ? $colorId : null, $talleId > 0 ? $talleId : null);
        }
    }
}
