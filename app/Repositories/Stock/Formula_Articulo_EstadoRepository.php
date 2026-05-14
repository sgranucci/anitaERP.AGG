<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Formula_Articulo_Estado;

class Formula_Articulo_EstadoRepository implements Formula_Articulo_EstadoRepositoryInterface
{
    protected $model;

    public function __construct(Formula_Articulo_Estado $model)
    {
        $this->model = $model;
    }

    public function create(array $data, int $formula_articulo_id)
    {
        if (! isset($data['fechas'], $data['estados'], $data['usuario_ids'], $data['observacionestados'])) {
            return null;
        }
        for ($i = 0; $i < count($data['fechas']); $i++) {
            if (empty($data['fechas'][$i])) {
                continue;
            }
            $this->model->create([
                'formula_articulo_id' => $formula_articulo_id,
                'fecha' => $data['fechas'][$i],
                'estado' => $data['estados'][$i],
                'usuario_id' => $data['usuario_ids'][$i],
                'observacion' => $data['observacionestados'][$i] ?? '',
            ]);
        }
    }

    public function creaEstado(int $formula_articulo_id, string $fecha, string $estado, int $usuario_id, ?string $observacion)
    {
        return $this->model->create([
            'formula_articulo_id' => $formula_articulo_id,
            'fecha' => $fecha,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'observacion' => $observacion ?? '',
        ]);
    }

    public function leeHistoria(int $formula_articulo_id)
    {
        return $this->model->query()
            ->where('formula_articulo_id', $formula_articulo_id)
            ->with('usuarios')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }
}
