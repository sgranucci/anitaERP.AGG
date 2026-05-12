<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Ordencompra_Estado;

class Ordencompra_EstadoRepository implements Ordencompra_EstadoRepositoryInterface
{
    public function __construct(private Ordencompra_Estado $model)
    {
    }

    public function create(array $data, int $ordencompra_id): void
    {
        if (! isset($data['fechas'], $data['estados'], $data['usuario_ids'], $data['observacionestados'])) {
            return;
        }
        for ($i = 0; $i < count($data['fechas']); $i++) {
            if (empty($data['fechas'][$i])) {
                continue;
            }
            $this->model->create([
                'ordencompra_id' => $ordencompra_id,
                'fecha' => $data['fechas'][$i],
                'estado' => $data['estados'][$i],
                'usuario_id' => $data['usuario_ids'][$i],
                'observacion' => $data['observacionestados'][$i] ?? '',
            ]);
        }
    }

    public function creaEstado(int $ordencompra_id, string $fecha, string $estado, int $usuario_id, string $observacion)
    {
        return $this->model->create([
            'ordencompra_id' => $ordencompra_id,
            'fecha' => $fecha,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'observacion' => $observacion,
        ]);
    }
}
