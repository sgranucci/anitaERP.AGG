<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Stock\FormulaArticuloInsumosCatalogoSupport;
use Illuminate\Support\Facades\DB;

class ArticuloUsoInsumoFormulaService
{
    /**
     * Verifica y opcionalmente asigna uso «INSUMO GASTRONOMIA» a artículos de fórmulas.
     *
     * @return array{
     *     uso_insumo_id: int|null,
     *     insumos_en_formulas: int,
     *     ya_catalogados: int,
     *     pendientes: list<array{
     *         articulo_id: int,
     *         sku: string,
     *         descripcion: string,
     *         uso_actual_id: int|null,
     *         uso_actual_nombre: string
     *     }>,
     *     actualizados: int,
     *     sin_uso_insumo_maestro: bool,
     *     advertencias: list<string>
     * }
     */
    public function verificarYActualizar(bool $aplicar = false, int $limite = 0): array
    {
        $usoInsumoId = ArticuloUsoInsumoSupport::idUsoInsumo();
        $resultado = [
            'uso_insumo_id' => $usoInsumoId,
            'insumos_en_formulas' => 0,
            'ya_catalogados' => 0,
            'pendientes' => [],
            'actualizados' => 0,
            'sin_uso_insumo_maestro' => $usoInsumoId === null || $usoInsumoId <= 0,
            'advertencias' => [],
        ];

        if ($resultado['sin_uso_insumo_maestro']) {
            $resultado['advertencias'][] = 'No existe el uso maestro «'
                .ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO
                .'» en la tabla usoarticulo.';

            return $resultado;
        }

        $articuloIds = FormulaArticuloInsumosCatalogoSupport::articuloIdsEnTodasLasFormulas();
        $resultado['insumos_en_formulas'] = count($articuloIds);

        if ($articuloIds === []) {
            return $resultado;
        }

        $articulos = Articulo::query()
            ->with('usoarticulos')
            ->whereIn('id', $articuloIds)
            ->orderBy('sku')
            ->get(['id', 'sku', 'descripcion', 'usoarticulo_id']);

        $pendientesIds = [];

        foreach ($articulos as $articulo) {
            if (ArticuloUsoInsumoSupport::esUsoInsumo((int) $articulo->usoarticulo_id)) {
                $resultado['ya_catalogados']++;

                continue;
            }

            $pendientesIds[] = (int) $articulo->id;
            $resultado['pendientes'][] = [
                'articulo_id' => (int) $articulo->id,
                'sku' => trim((string) $articulo->sku),
                'descripcion' => trim((string) $articulo->descripcion),
                'uso_actual_id' => $articulo->usoarticulo_id !== null ? (int) $articulo->usoarticulo_id : null,
                'uso_actual_nombre' => trim((string) ($articulo->usoarticulos->nombre ?? '—')),
            ];
        }

        $faltantesEnErp = array_diff($articuloIds, $articulos->pluck('id')->map(fn ($id) => (int) $id)->all());
        foreach ($faltantesEnErp as $articuloId) {
            $resultado['advertencias'][] = 'Artículo id '.$articuloId.' referenciado en fórmula pero inexistente en articulo.';
        }

        if ($pendientesIds === [] || ! $aplicar) {
            return $resultado;
        }

        if ($limite > 0) {
            $pendientesIds = array_slice($pendientesIds, 0, $limite);
        }

        DB::transaction(function () use ($pendientesIds, $usoInsumoId, &$resultado) {
            foreach (array_chunk($pendientesIds, 200) as $lote) {
                $actualizados = Articulo::query()
                    ->whereIn('id', $lote)
                    ->where(function ($q) use ($usoInsumoId) {
                        $q->whereNull('usoarticulo_id')
                            ->orWhere('usoarticulo_id', '<>', $usoInsumoId);
                    })
                    ->update(['usoarticulo_id' => $usoInsumoId]);

                $resultado['actualizados'] += (int) $actualizados;
            }
        });

        return $resultado;
    }

    /**
     * Artículos marcados como insumo pero que no figuran en ninguna fórmula (informativo).
     *
     * @return list<array{articulo_id:int,sku:string,descripcion:string}>
     */
    public function insumosSinFormula(int $limite = 50): array
    {
        $usoInsumoId = ArticuloUsoInsumoSupport::idUsoInsumo();
        if ($usoInsumoId === null || $usoInsumoId <= 0) {
            return [];
        }

        $enFormulas = FormulaArticuloInsumosCatalogoSupport::articuloIdsEnTodasLasFormulas();

        return Articulo::query()
            ->where('usoarticulo_id', $usoInsumoId)
            ->when($enFormulas !== [], fn ($q) => $q->whereNotIn('id', $enFormulas))
            ->orderBy('sku')
            ->limit(max(1, $limite))
            ->get(['id', 'sku', 'descripcion'])
            ->map(fn (Articulo $a) => [
                'articulo_id' => (int) $a->id,
                'sku' => trim((string) $a->sku),
                'descripcion' => trim((string) $a->descripcion),
            ])
            ->values()
            ->all();
    }
}
