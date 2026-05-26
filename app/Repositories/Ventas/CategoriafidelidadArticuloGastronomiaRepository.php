<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\CategoriafidelidadArticuloGastronomia;

class CategoriafidelidadArticuloGastronomiaRepository implements CategoriafidelidadArticuloGastronomiaRepositoryInterface
{
    public function __construct(
        private CategoriafidelidadArticuloGastronomia $model,
    ) {
    }

    public function syncFromRequest(array $data, int $categoriafidelidadId): void
    {
        $submittedIds = [];
        if (! empty($data['categoriafidelidad_articulo_ids']) && is_array($data['categoriafidelidad_articulo_ids'])) {
            foreach ($data['categoriafidelidad_articulo_ids'] as $lineaId) {
                if ($lineaId !== null && $lineaId !== '') {
                    $submittedIds[] = (int) $lineaId;
                }
            }
        }

        $existentes = $this->model->where('categoriafidelidad_id', $categoriafidelidadId)->pluck('id')->all();
        $aBorrar = array_diff($existentes, $submittedIds);
        if ($aBorrar !== []) {
            $this->model->whereIn('id', $aBorrar)->delete();
        }

        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            return;
        }

        $n = count($data['articulo_ids']);
        for ($i = 0; $i < $n; $i++) {
            $articuloId = $data['articulo_ids'][$i] ?? null;
            $lineaIdRaw = $data['categoriafidelidad_articulo_ids'][$i] ?? null;

            if ($articuloId === null || $articuloId === '') {
                if ($lineaIdRaw !== null && $lineaIdRaw !== '') {
                    $this->model->where('id', (int) $lineaIdRaw)
                        ->where('categoriafidelidad_id', $categoriafidelidadId)
                        ->delete();
                }

                continue;
            }

            $payload = [
                'categoriafidelidad_id' => $categoriafidelidadId,
                'articulo_id' => (int) $articuloId,
            ];

            if ($lineaIdRaw !== null && $lineaIdRaw !== '') {
                $this->model->where('id', (int) $lineaIdRaw)
                    ->where('categoriafidelidad_id', $categoriafidelidadId)
                    ->update($payload);
            } else {
                $this->model->create($payload);
            }
        }
    }

    public function reemplazarArticulos(int $categoriafidelidadId, array $articuloIds): void
    {
        $this->model->where('categoriafidelidad_id', $categoriafidelidadId)->delete();

        $insertados = [];
        foreach ($articuloIds as $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0 || isset($insertados[$articuloId])) {
                continue;
            }
            $insertados[$articuloId] = true;
            $this->model->create([
                'categoriafidelidad_id' => $categoriafidelidadId,
                'articulo_id' => $articuloId,
            ]);
        }
    }
}
