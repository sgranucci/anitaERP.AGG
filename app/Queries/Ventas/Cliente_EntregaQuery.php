<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Cliente_Entrega;

class Cliente_EntregaQuery implements Cliente_EntregaQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Entrega $cliente_entrega)
    {
        $this->model = $cliente_entrega;
    }

    public function traeCliente_EntregaporCliente_id($cliente_id)
    {
        return $this->model->select('id','nombre')->where('cliente_id',$cliente_id)->get();
    }

}

