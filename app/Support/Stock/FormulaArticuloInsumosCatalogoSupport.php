<?php

namespace App\Support\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Hijo;

/**
 * Artículos que intervienen como insumo en fórmulas (líneas directas y subfórmulas).
 */
final class FormulaArticuloInsumosCatalogoSupport
{
    private const MAX_PROFUNDIDAD = 25;

    /**
     * @return list<int>
     */
    public static function articuloIdsEnTodasLasFormulas(): array
    {
        $ids = [];
        $visitadas = [];

        foreach (Formula_Articulo::query()->orderBy('id')->pluck('id') as $formulaId) {
            self::colectarDesdeFormula((int) $formulaId, $ids, $visitadas, 0);
        }

        $ids = array_map('intval', array_keys($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, true>  $ids
     * @param  array<int, true>  $visitadas
     */
    private static function colectarDesdeFormula(int $formulaId, array &$ids, array &$visitadas, int $depth): void
    {
        if ($formulaId <= 0 || $depth > self::MAX_PROFUNDIDAD) {
            return;
        }

        if (isset($visitadas[$formulaId])) {
            return;
        }
        $visitadas[$formulaId] = true;

        $hijos = Formula_Articulo_Hijo::query()
            ->where('formula_articulo_id', $formulaId)
            ->orderBy('ordenopcional')
            ->orderBy('id')
            ->get(['articulo_id', 'formula_hija_id']);

        foreach ($hijos as $hijo) {
            $formulaHijaId = (int) ($hijo->formula_hija_id ?? 0);
            if ($formulaHijaId > 0) {
                self::colectarDesdeFormula($formulaHijaId, $ids, $visitadas, $depth + 1);

                continue;
            }

            $articuloId = (int) ($hijo->articulo_id ?? 0);
            if ($articuloId > 0) {
                $ids[$articuloId] = true;
            }
        }
    }
}
