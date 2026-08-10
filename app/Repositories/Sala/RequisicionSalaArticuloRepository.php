<?php

namespace App\Repositories\Sala;

use App\Models\Sala\RequisicionSalaArticulo;

class RequisicionSalaArticuloRepository implements RequisicionSalaArticuloRepositoryInterface
{
    public function __construct(protected RequisicionSalaArticulo $model)
    {
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function syncFromRequest(array $data, int $requisicion_sala_id): void
    {
        if (! isset($data['articulo_ids']) || ! is_array($data['articulo_ids'])) {
            $this->model->where('requisicion_sala_id', $requisicion_sala_id)->delete();

            return;
        }

        $idsExistentes = $this->model->where('requisicion_sala_id', $requisicion_sala_id)->pluck('id')->all();
        $idsExistentesFlip = array_flip($idsExistentes);
        $idsEntrantes = $data['requisicion_sala_articulo_ids'] ?? [];
        $idsAConservar = [];

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

            $payload = [
                'requisicion_sala_id' => $requisicion_sala_id,
                'articulo_id' => $articulo_id,
                'cantidad' => $cantidad,
                'precio' => (float) ($data['precios'][$i] ?? 0),
                'detalle' => $data['detalle_articulos'][$i] ?? '',
                'fueradeservicio' => ($data['fueradeservicios'][$i] ?? 'N') === 'S' ? 'S' : 'N',
                'uid' => $data['uids'][$i] ?? '',
                'destino' => $data['destinos'][$i] ?? 'S',
                'estado' => $data['estados_linea'][$i] ?? ' ',
                'numeroparte' => $data['numeropartes'][$i] ?? '',
            ];

            $idCandidato = $idsEntrantes[$i] ?? null;
            $idCandidato = ($idCandidato === null || $idCandidato === '') ? null : (int) $idCandidato;

            if ($idCandidato !== null && isset($idsExistentesFlip[$idCandidato])) {
                $registro = $this->model->where('id', $idCandidato)
                    ->where('requisicion_sala_id', $requisicion_sala_id)->first();
                if ($registro) {
                    $registro->update($payload);
                    $idsAConservar[] = $idCandidato;
                }
            } else {
                $nuevo = $this->model->create($payload);
                $idsAConservar[] = $nuevo->id;
            }
        }

        $queryEliminar = $this->model->where('requisicion_sala_id', $requisicion_sala_id);
        if ($idsAConservar !== []) {
            $queryEliminar->whereNotIn('id', $idsAConservar);
        }
        $queryEliminar->delete();
    }

    public function syncDatosMenoresFromRequest(array $data, int $requisicion_sala_id): void
    {
        if (! isset($data['requisicion_sala_articulo_ids']) || ! is_array($data['requisicion_sala_articulo_ids'])) {
            return;
        }

        $idsEntrantes = $data['requisicion_sala_articulo_ids'];
        $n = count($idsEntrantes);
        for ($i = 0; $i < $n; $i++) {
            $idLinea = $idsEntrantes[$i] ?? null;
            $idLinea = ($idLinea === null || $idLinea === '') ? null : (int) $idLinea;
            if ($idLinea === null || $idLinea <= 0) {
                continue;
            }

            $registro = $this->model->where('id', $idLinea)
                ->where('requisicion_sala_id', $requisicion_sala_id)
                ->first();
            if (! $registro) {
                continue;
            }

            $nuevoArticuloId = isset($data['articulo_ids'][$i]) && $data['articulo_ids'][$i] !== ''
                ? (int) $data['articulo_ids'][$i]
                : 0;

            $registro->update([
                'articulo_id' => $nuevoArticuloId > 0 ? $nuevoArticuloId : $registro->articulo_id,
                'detalle' => $data['detalle_articulos'][$i] ?? ($registro->detalle ?? ''),
                'uid' => $data['uids'][$i] ?? ($registro->uid ?? ''),
                'numeroparte' => $data['numeropartes'][$i] ?? ($registro->numeroparte ?? ''),
            ]);
        }
    }
}
