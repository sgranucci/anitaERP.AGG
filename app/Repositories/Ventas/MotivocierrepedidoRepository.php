<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Motivocierrepedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class MotivocierrepedidoRepository implements MotivocierrepedidoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Motivocierrepedido $motivocierrepedido)
    {
        $this->model = $motivocierrepedido;
    }

    public function all()
    {
        $hay_motivocierrepedidos = Motivocierrepedido::first();

        return $this->model->get();
    }

    public function create(array $data)
    {
        $motivocierrepedido = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $motivocierrepedido = $this->model->findOrFail($id)->update($data);

		return $motivocierrepedido;
    }

    public function delete($id)
    {
    	$motivocierrepedido = Motivocierrepedido::find($id);

        $motivocierrepedido = $this->model->destroy($id);

		return $motivocierrepedido;
    }

    public function find($id)
    {
        if (null == $motivocierrepedido = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $motivocierrepedido;
    }

    public function findOrFail($id)
    {
        if (null == $motivocierrepedido = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $motivocierrepedido;
    }
}
