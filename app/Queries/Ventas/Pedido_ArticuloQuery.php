<?php

namespace App\Queries\Ventas;

use App\Models\Ventas\Pedido_Articulo;

class Pedido_ArticuloQuery implements Pedido_ArticuloQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Articulo $pedido)
    {
        $this->model = $pedido;
    }

    public function leePedido_ArticuloporNumeroItem($pedido_id, $numeroitem)
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
