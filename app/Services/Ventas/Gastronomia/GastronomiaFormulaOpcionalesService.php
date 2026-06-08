<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Lista y validación de opcionales de fórmula (orden 1..N) para gastronomía.
 */
final class GastronomiaFormulaOpcionalesService
{
    /**
     * @return list<array{orden:int|string, opciones:list<array{articulo_id?:int, formula_hija_id?:int, sku:string, descripcion:string}>}>
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
            ->with([
                'formula_articulo_hijos.articulos',
                'formula_articulo_hijos.formula_hija.articulos',
            ])
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
                $item = $this->opcionDesdeHijo($h);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
            if ($items !== []) {
                $out[] = ['orden' => $orden, 'opciones' => $items];
            }
        }

        return $out;
    }

    /**
     * @return array{articulo_id?:int, formula_hija_id?:int, sku:string, descripcion:string}|null
     */
    private function opcionDesdeHijo(\App\Models\Stock\Formula_Articulo_Hijo $h): ?array
    {
        if ($h->articulo_id && $h->articulos) {
            return [
                'articulo_id' => (int) $h->articulo_id,
                'sku' => (string) $h->articulos->sku,
                'descripcion' => (string) $h->articulos->descripcion,
            ];
        }

        if ($h->formula_hija_id) {
            $sub = $h->formula_hija;
            $artSub = $sub?->articulos;
            $sku = $artSub ? (string) $artSub->sku : '';
            $desc = $artSub ? (string) $artSub->descripcion : '';

            if ($sku === '' && $desc === '') {
                $sku = 'F#'.$h->formula_hija_id;
                $desc = 'Subfórmula';
            }

            return [
                'formula_hija_id' => (int) $h->formula_hija_id,
                'sku' => $sku,
                'descripcion' => $desc,
            ];
        }

        return null;
    }

    /**
     * @param  array<string|int, mixed>  $opcionalesSeleccion  orden => articulo_id | f:{id} | array
     *
     * @throws \InvalidArgumentException
     */
    public function validarSeleccionOpcionales(Articulo $articulo, array $opcionalesSeleccion): void
    {
        $grupos = $this->gruposOpcionalesPorArticulo($articulo);

        foreach ($grupos as $grupo) {
            $ordenKey = (string) $grupo['orden'];
            $elegido = $opcionalesSeleccion[$ordenKey] ?? $opcionalesSeleccion[(int) $ordenKey] ?? null;
            if (GastronomiaFormulaOpcionalSeleccion::estaVacio($elegido)) {
                throw new \InvalidArgumentException('Debe seleccionar opcional para orden '.$ordenKey.' ('.$articulo->sku.').');
            }

            if (! GastronomiaFormulaOpcionalSeleccion::esOpcionValidaEnGrupo($elegido, $grupo['opciones'])) {
                throw new \InvalidArgumentException('Opcional inválido para orden '.$ordenKey.' ('.$articulo->sku.').');
            }
        }
    }

    /**
     * @param  iterable<int, \App\Models\Ventas\CuentaGastronomiaLinea>  $lineas
     * @return list<string>
     */
    public function erroresOpcionalesEnLineas(iterable $lineas): array
    {
        if (! FormulaArticuloGastronomia::opcionalesHabilitados()) {
            return [];
        }

        $errores = [];
        foreach ($lineas as $linea) {
            $art = $linea->articulo;
            if (! $art) {
                continue;
            }

            $grupos = $this->gruposOpcionalesPorArticulo($art);
            if ($grupos === []) {
                continue;
            }

            $opcMap = [];
            foreach (($linea->opcionales_json ?? []) as $k => $v) {
                $opcMap[(string) $k] = $v;
            }

            try {
                $this->validarSeleccionOpcionales($art, $opcMap);
            } catch (\InvalidArgumentException $e) {
                $errores[] = $e->getMessage()
                    .' — elimine el consumo y carguelo de nuevo en el POS (modal de opcionales).';
            }
        }

        return $errores;
    }
}
