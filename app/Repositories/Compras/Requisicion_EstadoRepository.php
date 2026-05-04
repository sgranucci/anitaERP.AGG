<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion_Estado;

class Requisicion_EstadoRepository implements Requisicion_EstadoRepositoryInterface
{
    protected $model;

    public function __construct(Requisicion_Estado $requisicion_estado)
    {
        $this->model = $requisicion_estado;
    }

    public function create(array $data, $requisicion_id)
    {
        if (!isset($data['fechas'], $data['estados'], $data['usuario_ids'], $data['observacionestados'])) {
            return null;
        }
        for ($i = 0; $i < count($data['fechas']); $i++) {
            if (empty($data['fechas'][$i])) {
                continue;
            }
            $this->model->create([
                'requisicion_id' => $requisicion_id,
                'fecha' => $data['fechas'][$i],
                'estado' => $data['estados'][$i],
                'usuario_id' => $data['usuario_ids'][$i],
                'observacion' => $data['observacionestados'][$i] ?? '',
            ]);
        }
    }

    public function creaEstado($requisicion_id, $fecha, $estado, $usuario_id, $observacion)
    {
        return $this->model->create([
            'requisicion_id' => $requisicion_id,
            'fecha' => $fecha,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'observacion' => $observacion,
        ]);
    }

    public function leeHistoriaRequisicion($requisicion_id)
    {
        return $this->model->select('id', 'requisicion_id', 'fecha', 'estado', 'usuario_id', 'observacion')
            ->where('requisicion_id', $requisicion_id)
            ->with('usuarios')
            ->get();
    }
}
