<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Articulo;

class Requisicion_ArticuloRepository implements Requisicion_ArticuloRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Articulo $requisicion_articulo)
    {
        $this->model = $requisicion_articulo;
    }

    public function createUnique(array $data)
    {
        return $this->model->create($data);
    }

    public function syncFromRequest(array $data, $requisicion_id)
    {
        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            $this->model->where('requisicion_id', $requisicion_id)->delete();

            return;
        }

        // IDs existentes de líneas para esta requisición; se usan para validar que un id
        // entrante realmente pertenezca a la requisición antes de hacer UPDATE.
        // El objetivo es preservar las líneas existentes y así no romper las claves foráneas
        // de tablas dependientes (p. ej. requisicion_presupuesto_articulo con ON DELETE CASCADE).
        $idsExistentes = $this->model->where('requisicion_id', $requisicion_id)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);

        $idsEntrantes = $data['requisicion_articulo_ids'] ?? [];

        $idsAConservar = [];
        $aActualizar = [];
        $aInsertar = [];

        $n = count($data['articulo_ids']);
        for ($i = 0; $i < $n; $i++) {
            $articulo_id = $data['articulo_ids'][$i] ?? null;
            if ($articulo_id === null || $articulo_id === '') {
                continue;
            }
            $cantidad = (float) ($data['cantidades'][$i] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $precio = (float) ($data['precios'][$i] ?? 0);
            $payload = [
                'requisicion_id' => $requisicion_id,
                'fechaentrega' => $data['fechaentrega_articulos'][$i] ?? $data['fechaentrega'] ?? $data['fecha'] ?? date('Y-m-d'),
                'articulo_id' => $articulo_id,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'moneda_id' => $data['moneda_linea_ids'][$i] ?? $data['moneda_id'],
                'cantidadalternativa' => (float) ($data['cantidadalternativas'][$i] ?? 0),
                'detalle' => $data['detalle_articulos'][$i] ?? '',
                'centrocostodestino_id' => $data['centrocostodestino_ids'][$i] ?? $data['centrocosto_id'],
                'preciooriginal' => (float) ($data['preciooriginales'][$i] ?? $precio),
                'motivoahorro' => $data['motivoahorros'][$i] ?? '',
                'partidagasto_id' => ! empty($data['partidagasto_ids'][$i]) ? $data['partidagasto_ids'][$i] : null,
                'capex_id' => ! empty($data['capex_ids'][$i]) ? $data['capex_ids'][$i] : null,
            ];

            $idCandidato = $idsEntrantes[$i] ?? null;
            $idCandidato = ($idCandidato === null || $idCandidato === '') ? null : (int) $idCandidato;

            if ($idCandidato !== null && isset($idsExistentesFlip[$idCandidato])) {
                $aActualizar[$idCandidato] = $payload;
                $idsAConservar[] = $idCandidato;
            } else {
                $aInsertar[] = $payload;
            }
        }

        $queryEliminar = $this->model->where('requisicion_id', $requisicion_id);
        if (! empty($idsAConservar)) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();

        foreach ($aActualizar as $id => $payload) {
            $registro = $this->model->where('id', $id)->where('requisicion_id', $requisicion_id)->first();
            if ($registro) {
                $registro->update($payload);
            }
        }

        foreach ($aInsertar as $payload) {
            $this->model->create($payload);
        }
    }
}
