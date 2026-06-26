<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Asigna empresa al maestro de artículo cuando el stock opera en un depósito
 * de una empresa y el ítem legacy no tenía empresa_id.
 */
final class ArticuloEmpresaAsignacionSupport
{
    public static function asignarSiVacia(int $articuloId, int $empresaId): void
    {
        if ($articuloId <= 0 || $empresaId <= 0) {
            return;
        }

        Articulo::query()
            ->whereKey($articuloId)
            ->where(function ($query) {
                $query->whereNull('empresa_id')->orWhere('empresa_id', 0);
            })
            ->update(['empresa_id' => $empresaId]);
    }

    /**
     * @param  list<int>  $articuloIds
     */
    public static function asignarSiVaciaLote(array $articuloIds, int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $articuloIds),
            fn (int $id) => $id > 0
        )));

        if ($ids === []) {
            return;
        }

        Articulo::query()
            ->whereIn('id', $ids)
            ->where(function ($query) {
                $query->whereNull('empresa_id')->orWhere('empresa_id', 0);
            })
            ->update(['empresa_id' => $empresaId]);
    }
}
