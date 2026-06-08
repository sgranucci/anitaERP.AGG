<?php

namespace App\Repositories\Sala;

use App\Models\Sala\RequisicionSalaEstado;

class RequisicionSalaEstadoRepository implements RequisicionSalaEstadoRepositoryInterface
{
    public function __construct(protected RequisicionSalaEstado $model)
    {
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data, $requisicion_sala_id)
    {
        if (! isset($data['fechas'], $data['estados'], $data['usuario_ids'], $data['observacionestados'])) {
            return null;
        }
        for ($i = 0; $i < count($data['fechas']); $i++) {
            if (empty($data['fechas'][$i])) {
                continue;
            }
            $this->model->create([
                'requisicion_sala_id' => $requisicion_sala_id,
                'fecha' => $data['fechas'][$i],
                'estado' => $data['estados'][$i],
                'usuario_id' => $data['usuario_ids'][$i],
                'observacion' => $data['observacionestados'][$i] ?? '',
            ]);
        }
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

    public function creaEstado($requisicion_sala_id, $fecha, $estado, $usuario_id, $observacion)
    {
        return $this->model->create([
            'requisicion_sala_id' => $requisicion_sala_id,
            'fecha' => $fecha,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'observacion' => $observacion,
        ]);
    }

    public function leeHistoria(int $requisicion_sala_id)
    {
        return $this->model->select('id', 'requisicion_sala_id', 'fecha', 'estado', 'usuario_id', 'observacion')
            ->where('requisicion_sala_id', $requisicion_sala_id)
            ->with('usuarios')
            ->orderBy('fecha')
            ->get();
    }
}
