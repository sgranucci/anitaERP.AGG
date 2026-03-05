<?php

namespace App\Repositories\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\Pedido_CombinacionQueryInterface;
use App\Models\Ventas\Pedido_Combinacion_Estado;
use App\Models\Ventas\Motivocierrepedido;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Pedido_Combinacion_EstadoRepository implements Pedido_Combinacion_EstadoRepositoryInterface
{
    protected $model;
	protected $pedidoQuery;
	protected $pedidoCombinacionQuery;
    protected $keyField = 'codigo';
    protected $tableAnita = 'pendmov';
    protected $keyFieldAnita = ['penm_sucursal', 'penm_nro'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Combinacion_Estado $pedido_combinacion_estado,
								PedidoQueryInterface $pedidoquery,
								Pedido_CombinacionQueryInterface $pedidocombinacionquery)
    {
        $this->model = $pedido_combinacion_estado;
        $this->pedidoQuery = $pedidoquery;
        $this->pedidoCombinacionQuery = $pedidocombinacionquery;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($data)
	{
        $pedido_combinacion_estado = $this->model->create($data);

		return($pedido_combinacion_estado);
    }

    public function delete($id)
    {
    	$pedido_combinacion_estado = $this->model->find($id);

        $pedido_combinacion_estado = $this->model->destroy($id);
		return $pedido_combinacion_estado;
    }

    public function find($id)
    {
        if (null == $pedido_combinacion_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_combinacion_estado;
    }

    public function findOrFail($id)
    {
        if (null == $pedido_combinacion_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_combinacion_estado;
    }

    public function traeEstado($pedido_combinacion_id)
    {
    	$pedido_combinacion_estado = $this->model->where('pedido_combinacion_id', $pedido_combinacion_id)
                                    ->orderBy('id','desc')->first();

		return $pedido_combinacion_estado;
    }

}
