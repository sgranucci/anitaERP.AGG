<?php

namespace App\Services\Stock\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Support\Stock\FormulaArticuloGastronomia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Lista y validación de opcionales de fórmula (orden 1..N) para gastronomía.
 */
final class GastronomiaFormulaOpcionalesService
{
    /**
     * @return list<array{orden:int|string, opciones:list<array{articulo_id:int, sku:string, descripcion:string}>}>
     */
    public function gruposOpcionalesPorArticulo(Articulo $articulo): array
    {
        if (! FormulaArticuloGastronomia::opcionalesHabilitados()) {
            return [];
        }

        if (! Schema::hasColumn('formula_articulo_hijo', 'ordenopcional')) {
            return [];
        }

        if (! $articulo->formula) {
            return [];
        }

        $formula = Formula_Articulo::query()
            ->with(['formula_articulo_hijos.articulos'])
            ->find($articulo->formula);

        if (! $formula) {
            return [];
        }

        /** @var Collection<int, \App\Models\Stock\Formula_Articulo_Hijo> $opc */
        $opc = $formula->formula_articulo_hijos->where('esopcional', true)->sortBy([
            ['ordenopcional', 'asc'],
            ['id', 'asc'],
        ]);

        if ($opc->isEmpty()) {
            return [];
        }

        /** @var Collection<string|int, Collection<int, \App\Models\Stock\Formula_Articulo_Hijo>> $porOrden */
        $porOrden = $opc->groupBy(fn ($h) => $h->ordenopcional ?? 0);

        $out = [];
        foreach ($porOrden->sortKeys() as $orden => $grupo) {
            $items = [];
            foreach ($grupo as $h) {
                if (! $h->articulo_id || ! $h->articulos) {
                    continue;
                }
                $items[] = [
                    'articulo_id' => (int) $h->articulo_id,
                    'sku' => (string) $h->articulos->sku,
                    'descripcion' => (string) $h->articulos->descripcion,
                ];
            }
            if ($items !== []) {
                $out[] = ['orden' => $orden, 'opciones' => $items];
            }
        }

        return $out;
    }

    /**
     * @param  array<string|int, int|null>  $opcionalesSeleccion  orden => articulo_id
     *
     * @throws \InvalidArgumentException
     */
    public function validarSeleccionOpcionales(Articulo $articulo, array $opcionalesSeleccion): void
    {
        $grupos = $this->gruposOpcionalesPorArticulo($articulo);

        foreach ($grupos as $grupo) {
            $ordenKey = (string) $grupo['orden'];
            $elegido = $opcionalesSeleccion[$ordenKey] ?? null;
            if ($elegido === null || $elegido === 0) {
                throw new \InvalidArgumentException('Debe seleccionar opcional para orden '.$ordenKey.' ('.$articulo->sku.').');
            }

            $idsValidos = collect($grupo['opciones'])->pluck('articulo_id')->all();
            if (! in_array((int) $elegido, $idsValidos, true)) {
                throw new \InvalidArgumentException('Opcional inválido para orden '.$ordenKey.' ('.$articulo->sku.').');
            }
        }
    }
}
