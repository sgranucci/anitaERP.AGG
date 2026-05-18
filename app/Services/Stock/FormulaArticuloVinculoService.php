<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Support\Stock\FormulaArticuloSku;
use Illuminate\Support\Facades\DB;

class FormulaArticuloVinculoService
{
    /**
     * Vincula formula_articulo.articulo_id y articulo.formula según código de fórmula → SKU V####.
     *
     * @return array{
     *     formulas_procesadas: int,
     *     formulas_vinculadas: int,
     *     articulos_actualizados: int,
     *     articulos_desvinculados: int,
     *     articulos_corregidos: int,
     *     sin_articulo: list<string>,
     *     advertencias: list<string>
     * }
     */
    public function vincularPorCodigoSku(bool $dryRun = false): array
    {
        $stats = [
            'formulas_procesadas' => 0,
            'formulas_vinculadas' => 0,
            'articulos_actualizados' => 0,
            'articulos_desvinculados' => 0,
            'articulos_corregidos' => 0,
            'sin_articulo' => [],
            'advertencias' => [],
        ];

        DB::beginTransaction();
        try {
            foreach (Formula_Articulo::query()->orderBy('id')->cursor() as $formula) {
                $stats['formulas_procesadas']++;
                $this->vincularUnaFormula($formula, $stats, $dryRun);
            }

            $this->corregirArticulosPorSku($stats, $dryRun);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function vincularUnaFormula(Formula_Articulo $formula, array &$stats, bool $dryRun): void
    {
        $codigo = FormulaArticuloSku::codigoNumericoDesdeFormula($formula);
        if ($codigo === null || $codigo <= 0) {
            return;
        }

        $skuEsperado = FormulaArticuloSku::skuDesdeCodigo($codigo);
        $articulo = Articulo::query()
            ->where('sku', $skuEsperado)
            ->orWhereRaw('UPPER(sku) = ?', [strtoupper($skuEsperado)])
            ->orderBy('id')
            ->first();

        if (! $articulo) {
            $stats['sin_articulo'][] = 'Fórmula ERP #'.$formula->id.' (código Anita '.$codigo.'): no existe artículo con SKU '.$skuEsperado;

            return;
        }

        $cambioFormula = (int) $formula->articulo_id !== (int) $articulo->id;
        $cambioArticulo = (int) $articulo->formula !== (int) $formula->id;

        if (! $dryRun) {
            if ($cambioFormula) {
                Formula_Articulo::query()
                    ->where('id', $formula->id)
                    ->update(['articulo_id' => $articulo->id]);
            }

            if ($cambioArticulo) {
                Articulo::query()
                    ->where('id', $articulo->id)
                    ->update(['formula' => $formula->id]);
                $stats['articulos_actualizados']++;
            }

            $desvinculados = Articulo::query()
                ->where('formula', $formula->id)
                ->where('id', '!=', $articulo->id)
                ->get(['id', 'sku']);

            foreach ($desvinculados as $otro) {
                Articulo::query()->where('id', $otro->id)->update(['formula' => 0]);
                $stats['articulos_desvinculados']++;
                $stats['advertencias'][] = 'Artículo '.$otro->sku.' (id '.$otro->id.') tenía formula='.$formula->id.' pero la fórmula corresponde a '.$skuEsperado.'; se limpió articulo.formula.';
            }
        }

        if ($cambioFormula || $cambioArticulo) {
            $stats['formulas_vinculadas']++;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function corregirArticulosPorSku(array &$stats, bool $dryRun): void
    {
        $prefijo = FormulaArticuloSku::prefijo();

        Articulo::query()
            ->where('sku', 'like', $prefijo.'%')
            ->orderBy('id')
            ->chunkById(300, function ($articulos) use (&$stats, $dryRun) {
                foreach ($articulos as $articulo) {
                    $codigo = FormulaArticuloSku::codigoDesdeSku((string) $articulo->sku);
                    if ($codigo === null) {
                        continue;
                    }

                    $formula = Formula_Articulo::query()
                        ->where(function ($q) use ($codigo) {
                            $q->where('anita_stkcm_formula', $codigo)
                                ->orWhere('codigo', (string) $codigo);
                        })
                        ->orderByDesc('id')
                        ->first();

                    if (! $formula) {
                        continue;
                    }

                    $cambioArticulo = (int) $articulo->formula !== (int) $formula->id;
                    $cambioFormula = (int) $formula->articulo_id !== (int) $articulo->id;

                    if (! $cambioArticulo && ! $cambioFormula) {
                        continue;
                    }

                    if (! $dryRun) {
                        if ($cambioArticulo) {
                            Articulo::query()
                                ->where('id', $articulo->id)
                                ->update(['formula' => $formula->id]);
                        }
                        if ($cambioFormula) {
                            Formula_Articulo::query()
                                ->where('id', $formula->id)
                                ->update(['articulo_id' => $articulo->id]);
                        }
                    }

                    $stats['articulos_corregidos']++;
                }
            });
    }
}
