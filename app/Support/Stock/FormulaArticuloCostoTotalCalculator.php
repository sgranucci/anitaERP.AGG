<?php

namespace App\Support\Stock;

use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Hijo;
use Illuminate\Support\Collection;

/**
 * Costo total de fórmula: Σ (cantidad × factor costo × mult. padre × precio últ. compra).
 * Subfórmulas: recursivo. Opcionales (gastronomía): 1.º de cada orden 1..N si no hay selección explícita.
 */
final class FormulaArticuloCostoTotalCalculator
{
    private const MAX_DEPTH = 25;

    /**
     * @param  Collection<int, Formula_Articulo>  $formulasPorId
     * @param  array<string, float|null>  $preciosPorSku  clave = articulo.sku
     * @param  array<string|int, int|null>|null  $opcionalesPorOrden  orden => articulo_id (gastronomía)
     * @param  list<int>  $pilafFormulas  detección de ciclos
     */
    public function calcular(
        int $formulaArticuloId,
        Collection $formulasPorId,
        array $preciosPorSku,
        ?array $opcionalesPorOrden = null,
        float $multiplicadorPadre = 1.0,
        int $depth = 0,
        array $pilafFormulas = [],
    ): FormulaArticuloCostoTotalResult {
        if ($depth > self::MAX_DEPTH) {
            return new FormulaArticuloCostoTotalResult(
                0.0,
                false,
                ['Fórmula demasiado anidada (posible ciclo).'],
            );
        }

        if (in_array($formulaArticuloId, $pilafFormulas, true)) {
            return new FormulaArticuloCostoTotalResult(
                0.0,
                false,
                ['Ciclo detectado en subfórmulas (#'.$formulaArticuloId.').'],
            );
        }

        /** @var Formula_Articulo|null $formula */
        $formula = $formulasPorId->get($formulaArticuloId);
        if (! $formula) {
            return new FormulaArticuloCostoTotalResult(
                0.0,
                false,
                ['Fórmula #'.$formulaArticuloId.' no encontrada.'],
            );
        }

        $cantidadUnidad = (float) ($formula->cantidadunidad ?? 1);
        $pilaf = [...$pilafFormulas, $formulaArticuloId];
        $acumulado = FormulaArticuloCostoTotalResult::vacio($cantidadUnidad);

        $hijos = $this->hijosParaCosto($formula->formula_articulo_hijos, $opcionalesPorOrden);

        foreach ($hijos as $hijo) {
            $parcial = $this->procesarHijo(
                $hijo,
                $formulasPorId,
                $preciosPorSku,
                $multiplicadorPadre,
                $depth,
                $pilaf,
                $opcionalesPorOrden,
            );
            $acumulado = $acumulado->combinar($parcial);
        }

        return new FormulaArticuloCostoTotalResult(
            $acumulado->total,
            $acumulado->completo,
            $acumulado->advertencias,
            $cantidadUnidad,
        );
    }

    /**
     * @param  Collection<int, Formula_Articulo_Hijo>  $hijos
     * @param  array<string|int, int|null>|null  $opcionalesPorOrden
     * @return Collection<int, Formula_Articulo_Hijo>
     */
    public function hijosParaCosto(Collection $hijos, ?array $opcionalesPorOrden): Collection
    {
        $out = $hijos->where('esopcional', false)->values();

        if (! FormulaArticuloGastronomia::opcionalesHabilitados()) {
            return $out;
        }

        $opcionales = $hijos->where('esopcional', true)->sortBy([
            ['ordenopcional', 'asc'],
            ['id', 'asc'],
        ]);

        /** @var Collection<string, Collection<int, Formula_Articulo_Hijo>> $porOrden */
        $porOrden = $opcionales->groupBy(fn (Formula_Articulo_Hijo $h) => (string) ($h->ordenopcional ?? '0'));

        foreach ($porOrden->sortKeys() as $orden => $grupo) {
            if ($opcionalesPorOrden !== null) {
                $elegido = $opcionalesPorOrden[$orden] ?? $opcionalesPorOrden[(int) $orden] ?? null;
                if ($elegido === null || (int) $elegido === 0) {
                    continue;
                }
                $match = $grupo->firstWhere('articulo_id', (int) $elegido);
                if ($match) {
                    $out->push($match);
                }

                continue;
            }

            $primero = $grupo->first();
            if ($primero) {
                $out->push($primero);
            }
        }

        return $out->values();
    }

    /**
     * @param  Collection<int, Formula_Articulo>  $formulasPorId
     * @param  array<string, float|null>  $preciosPorSku
     * @param  array<string|int, int|null>|null  $opcionalesPorOrden
     * @param  list<int>  $pilafFormulas
     */
    private function procesarHijo(
        Formula_Articulo_Hijo $hijo,
        Collection $formulasPorId,
        array $preciosPorSku,
        float $multiplicadorPadre,
        int $depth,
        array $pilafFormulas,
        ?array $opcionalesPorOrden,
    ): FormulaArticuloCostoTotalResult {
        $factorLinea = (float) $hijo->cantidad * FormulaArticuloFactorCosto::efectivo($hijo->factorcosto);
        $mult = $multiplicadorPadre * $factorLinea;

        if ($hijo->formula_hija_id) {
            return $this->calcular(
                (int) $hijo->formula_hija_id,
                $formulasPorId,
                $preciosPorSku,
                [],
                $mult,
                $depth + 1,
                $pilafFormulas,
            );
        }

        if (! $hijo->articulo_id) {
            return FormulaArticuloCostoTotalResult::vacio();
        }

        $sku = trim((string) (optional($hijo->articulos)->sku ?? ''));
        if ($sku === '') {
            return new FormulaArticuloCostoTotalResult(
                0.0,
                false,
                ['Línea #'.$hijo->id.' sin SKU de artículo.'],
            );
        }

        $precio = $preciosPorSku[$sku] ?? null;
        if ($precio === null) {
            return new FormulaArticuloCostoTotalResult(
                0.0,
                false,
                ['Sin precio de última compra para SKU '.$sku.' (Anita).'],
            );
        }

        return new FormulaArticuloCostoTotalResult(
            $mult * (float) $precio,
            true,
        );
    }
}
