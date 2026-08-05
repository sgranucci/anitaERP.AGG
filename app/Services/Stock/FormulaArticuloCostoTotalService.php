<?php

namespace App\Services\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\FormulaArticuloCostoTotalCalculator;
use App\Support\Stock\FormulaArticuloCostoTotalResult;
use Illuminate\Support\Collection;

/**
 * Orquesta precios de última compra (ERP → Anita → artículo) y {@see FormulaArticuloCostoTotalCalculator}.
 */
class FormulaArticuloCostoTotalService
{
    private const MAX_DEPTH = 25;

    public function __construct(
        private FormulaArticuloCostoTotalCalculator $calculator,
    ) {}

    /**
     * @param  array<string|int, int|null>|null  $opcionalesPorOrden  orden => articulo_id; null = 1.º opcional por nivel
     */
    public function calcular(int $formulaArticuloId, ?array $opcionalesPorOrden = null): FormulaArticuloCostoTotalResult
    {
        $formulaIds = [];
        $skus = [];
        $this->recolectarArbol($formulaArticuloId, $formulaIds, $skus, [], $opcionalesPorOrden, 0);

        if ($formulaIds === []) {
            return FormulaArticuloCostoTotalResult::vacio();
        }

        $formulasPorId = $this->cargarFormulas(array_unique($formulaIds));
        $precios = ArticuloPrecioUltimaCompraSupport::resolverPreciosPorSkus(array_unique($skus));

        return $this->calculator->calcular(
            $formulaArticuloId,
            $formulasPorId,
            $precios,
            $opcionalesPorOrden,
        );
    }

    /**
     * Asigna {@see Formula_Articulo::$costo_total_result} (dinámico, no persistido).
     *
     * @param  iterable<Formula_Articulo|object>  $formulas
     */
    public function enriquecerFormulasConCostoTotal(iterable $formulas, ?array $opcionalesPorOrden = null): void
    {
        $items = $formulas instanceof Collection ? $formulas : collect($formulas);
        if ($items->isEmpty()) {
            return;
        }

        $todosFormulaIds = [];
        $todosSkus = [];
        foreach ($items as $formula) {
            $fid = (int) ($formula->id ?? 0);
            if ($fid <= 0) {
                continue;
            }
            $this->recolectarArbol($fid, $todosFormulaIds, $todosSkus, [], $opcionalesPorOrden, 0);
        }

        if ($todosFormulaIds === []) {
            return;
        }

        $formulasPorId = $this->cargarFormulas(array_unique($todosFormulaIds));
        $precios = ArticuloPrecioUltimaCompraSupport::resolverPreciosPorSkus(array_unique($todosSkus));

        foreach ($items as $formula) {
            $fid = (int) ($formula->id ?? 0);
            if ($fid <= 0) {
                continue;
            }
            $formula->costo_total_result = $this->calculator->calcular(
                $fid,
                $formulasPorId,
                $precios,
                $opcionalesPorOrden,
            );
        }
    }

    /**
     * @param  list<int>  $formulaIds
     * @param  list<string>  $skus
     * @param  list<int>  $pilafFormulas
     * @param  array<string|int, int|null>|null  $opcionalesPorOrden
     */
    private function recolectarArbol(
        int $formulaId,
        array &$formulaIds,
        array &$skus,
        array $pilafFormulas,
        ?array $opcionalesPorOrden,
        int $depth,
    ): void {
        if ($formulaId <= 0 || in_array($formulaId, $pilafFormulas, true) || $depth > self::MAX_DEPTH) {
            return;
        }

        $formulaIds[] = $formulaId;
        $pilaf = [...$pilafFormulas, $formulaId];

        $formula = Formula_Articulo::query()
            ->with(['formula_articulo_hijos.articulos'])
            ->find($formulaId);

        if (! $formula) {
            return;
        }

        $hijos = $this->calculator->hijosParaCosto($formula->formula_articulo_hijos, $opcionalesPorOrden);

        foreach ($hijos as $hijo) {
            $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
            if ($sku !== '') {
                $skus[] = $sku;
            }
            if ($hijo->formula_hija_id) {
                $this->recolectarArbol(
                    (int) $hijo->formula_hija_id,
                    $formulaIds,
                    $skus,
                    $pilaf,
                    null,
                    $depth + 1,
                );
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Formula_Articulo>
     */
    private function cargarFormulas(array $ids): Collection
    {
        return Formula_Articulo::query()
            ->with([
                'formula_articulo_hijos.articulos',
                'formula_articulo_hijos.formula_hija',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
