<?php

namespace App\Repositories\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\Pedido_ArticuloQueryInterface;
use App\Models\Ventas\Pedido_Articulo_Estado;
use App\Models\Ventas\Motivocierrepedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Pedido_Articulo_EstadoRepository implements Pedido_Articulo_EstadoRepositoryInterface
{
    protected $model;
	protected $pedidoQuery;
	protected $pedidoarticuloQuery;
    protected $keyField = 'codigo';
    protected $tableAnita = 'pendmov';
    protected $keyFieldAnita = ['penm_sucursal', 'penm_nro'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Articulo_Estado $pedido_articulo_estado,
								PedidoQueryInterface $pedidoquery,
								Pedido_ArticuloQueryInterface $pedidoarticulocionquery)
    {
        $this->model = $pedido_articulo_estado;
        $this->pedidoQuery = $pedidoquery;
        $this->pedidoarticuloQuery = $pedidoarticulocionquery;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($data)
	{
        $pedido_articulo_estado = $this->model->create($data);

		return($pedido_articulo_estado);
    }

    public function delete($id)
    {
    	$pedido_articulo_estado = $this->model->find($id);

        $pedido_articulo_estado = $this->model->destroy($id);
		return $pedido_articulo_estado;
    }

    public function find($id)
    {
        if (null == $pedido_articulo_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_articulo_estado;
    }

    public function findOrFail($id)
    {
        if (null == $pedido_articulo_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_articulo_estado;
    }

    public function traeEstado($pedido_articulo_id)
    {
    	$pedido_articulo_estado = $this->model->where('pedido_articulo_id', $pedido_articulo_id)
                                    ->orderBy('id','desc')->first();

		return $pedido_articulo_estado;
    }

}
