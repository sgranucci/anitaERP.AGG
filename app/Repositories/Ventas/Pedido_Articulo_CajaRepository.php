<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Pedido_Articulo_Caja;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Pedido_Articulo_CajaRepository implements Pedido_Articulo_CajaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Articulo_Caja $pedido_articulo_caja)
    {
        $this->model = $pedido_articulo_caja;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($data)
	{
        $pedido_articulo_caja = $this->model->create($data);

		return($pedido_articulo_caja);
    }

    public function update($data, $id)
    {
		$pedido_articulo_caja = $this->model->find($id)->first();
		
		$pedido_articulo_caja = $pedido_articulo_caja->update($data);

		return $pedido_articulo_caja;
    }

    public function delete($id)
    {
    	$pedido_articulo_caja = $this->model->find($id);

        $pedido_articulo_caja = $this->model->destroy($id);
		return $pedido_articulo_caja;
    }

    public function find($id)
    {
        if (null == $pedido_articulo_caja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_articulo_caja;
    }

    public function findOrFail($id)
    {
        if (null == $pedido_articulo_caja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_articulo_caja;
    }

    public function findPorPedidoId($id)
    {
        $pedido_articulo_caja = $this->model
                                    ->select('pedido_articulo_caja.id', 'pedido_articulo.pedido_id')
                                    ->join('pedido_articulo', 'pedido_articulo.id', 'pedido_articulo_caja.pedido_articulo_id')
                                    ->where('pedido_articulo.pedido_id', $id)->get();

        return $pedido_articulo_caja;
    }

    public function deletePorPedidoId($id)
    {
        $pedido_articulo_caja = $this->model
                                    ->select('pedido_articulo_caja.id', 'pedido_articulo.pedido_id')
                                    ->join('pedido_articulo', 'pedido_articulo.id', 'pedido_articulo_caja.pedido_articulo_id')
                                    ->where('pedido_articulo.pedido_id', $id)->delete();

        return $pedido_articulo_caja;
    }    

}
