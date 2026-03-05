<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Pedido_Combinacion;

class Pedido_CombinacionQuery implements Pedido_CombinacionQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Combinacion $pedido)
    {
        $this->model = $pedido;
    }

    public function leePedido_CombinacionporNumeroItem($pedido_id, $numeroitem)
    {
        return $this->model->select('id')
					->where('pedido_id', $pedido_id)
					->where('numeroitem', $numeroitem)
					->first();
    }

    public function first()
    {
        return $this->model->first();
	}

    public function all()
    {
        return $this->model->with('pedido_combinaciones')->get();
    }

}
