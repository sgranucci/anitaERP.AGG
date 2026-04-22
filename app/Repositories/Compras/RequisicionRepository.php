<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Requisicion;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class RequisicionRepository implements RequisicionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Requisicion $ordenventa)
    {
        $this->model = $ordenventa;
    }

    public function create(array $data)
    {
		$data['numeroordenventa'] = self::ultimaRequisicion($data['empresa_id']);

		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$ordenventa = $this->model->findOrFail($id)->update($data);

		return $ordenventa;
    }

    public function delete($id)
    {
		$ordenventa = $this->model->findOrFail($id);

		if ($ordenventa)
        	$ordenventa = $this->model->destroy($id);

		return $ordenventa;
    }

    public function find($id)
    {
        if (null == $ordenventa = $this->model->with("ordenventa_estados")
									->with("ordenventa_cuotas")
									->with("ordenventa_conceptos")
									->with("ordenventa_archivos")
									->with("empresas")
									->with("centrocostos")
									->with("monedas")
									->with("clientes")
									->with("localidades")
									->with("provincias")
									->with("paises")
									->with("formapagos")
									->with("ventas")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return($ordenventa);
	}

    public function findOrFail($id)
    {
        if (null == $ordenventa = $this->model->with("ordenventa_estados")
									->with("ordenventa_cuotas")
									->with("ordenventa_conceptos")
									->with("ordenventa_archivos")
									->with("empresas")
									->with("centrocostos")
									->with("monedas")
									->with("clientes")
									->with("localidades")
									->with("provincias")
									->with("paises")
									->with("formapagos")
									->with("ventas")
									->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $ordenventa;
    }

	// Devuelve ultimo numero de ordenventa + 1
	private function ultimaRequisicion($empresa_id)
	{
		$ordenventa = $this->model->select('numeroordenventa')->where('empresa_id', $empresa_id)->where('deleted_at', null)
							->orderBy('numeroordenventa', 'desc')->first();
		
		$numeroordenventa = 0;
        if ($ordenventa) 
		{
			$numeroordenventa = $ordenventa->numeroordenventa;
			$numeroordenventa = $numeroordenventa + 1;
		}
		else	
			$numeroordenventa = 1;

		return $numeroordenventa;
	}	

	public function apruebaRequisicion($ordenventa_id)
	{
		$estado = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];

		// Graba estado de aprobacion
		$data = [];
	   	$data['fechas'][] = Carbon::now();
	   	$data['estados'][] = $estado;
		$data['usuario_ids'][] = Auth::user()->id;
	   	$data['observacionestados'][] = "Orden de Venta Aprobada";

		$ordenventa_estado = $this->ordenventa_estadoRepository->create($data, $ordenventa_id);

		return Self::update(['estado' => $estado], $id);
	}
}
