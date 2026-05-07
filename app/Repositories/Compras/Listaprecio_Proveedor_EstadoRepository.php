<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Listaprecio_Proveedor_Estado;

class Listaprecio_Proveedor_EstadoRepository implements Listaprecio_Proveedor_EstadoRepositoryInterface
{
    protected $model;

    public function __construct(Listaprecio_Proveedor_Estado $model)
    {
        $this->model = $model;
    }

    public function createInicial($listaprecio_proveedor_id, $estado, $usuario_id, $observacion)
    {
        return $this->model->create([
            'listaprecio_proveedor_id' => $listaprecio_proveedor_id,
            'estado' => $estado,
            'usuario_id' => $usuario_id,
            'observacion' => $observacion,
        ]);
    }

    public function creaEstado($listaprecio_proveedor_id, $estado, $usuario_id, $observacion)
    {
        return $this->createInicial($listaprecio_proveedor_id, $estado, $usuario_id, $observacion);
    }

    public function leeHistoria($listaprecio_proveedor_id)
    {
        return $this->model->select('id', 'listaprecio_proveedor_id', 'estado', 'usuario_id', 'observacion', 'created_at')
            ->where('listaprecio_proveedor_id', $listaprecio_proveedor_id)
            ->with('usuarios')
            ->orderBy('id', 'desc')
            ->get();
    }
}
