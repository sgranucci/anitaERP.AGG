<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Ordencompra_Articulo;

class Ordencompra_ArticuloRepository implements Ordencompra_ArticuloRepositoryInterface
{
    public function __construct(private Ordencompra_Articulo $model)
    {
    }

    public function syncFromRequest(array $data, int $ordencompra_id): void
    {
        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            $this->model->where('ordencompra_id', $ordencompra_id)->delete();

            return;
        }

        $idsExistentes = $this->model->where('ordencompra_id', $ordencompra_id)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);
        $idsEntrantes = $data['ordencompra_articulo_ids'] ?? [];
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
            $cot = (float) ($data['cotizaciones_linea'][$i] ?? 1);
            if ($cot <= 0) {
                $cot = 1.0;
            }

            $payload = [
                'ordencompra_id' => $ordencompra_id,
                'fechaentrega' => $data['fechaentrega_articulos'][$i] ?? $data['fechaentrega'] ?? $data['fecha'] ?? date('Y-m-d'),
                'articulo_id' => $articulo_id,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'moneda_id' => $data['moneda_linea_ids'][$i] ?? $data['moneda_id'] ?? 1,
                'cotizacion' => $cot,
                'descuento' => isset($data['descuentos_linea'][$i]) ? (float) $data['descuentos_linea'][$i] : null,
                'cantidadalternativa' => (float) ($data['cantidadalternativas'][$i] ?? 0),
                'detalle' => $data['detalle_articulos'][$i] ?? '',
                'centrocostodestino_id' => $data['centrocostodestino_ids'][$i] ?? $data['centrocosto_id'],
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

        $queryEliminar = $this->model->where('ordencompra_id', $ordencompra_id);
        if (! empty($idsAConservar)) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();

        foreach ($aActualizar as $id => $payload) {
            $registro = $this->model->where('id', $id)->where('ordencompra_id', $ordencompra_id)->first();
            if ($registro) {
                $registro->update($payload);
            }
        }

        foreach ($aInsertar as $payload) {
            $this->model->create($payload);
        }
    }
}
