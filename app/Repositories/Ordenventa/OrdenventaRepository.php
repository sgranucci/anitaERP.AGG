<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Ordenventa;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class OrdenventaRepository implements OrdenventaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Ordenventa $ordenventa)
    {
        $this->model = $ordenventa;
    }

    public function create(array $data)
    {
		$data['numeroordenventa'] = self::ultimaOrdenventa($data['empresa_id']);

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
									->with("ordenventa_archivos")
									->with("empresas")
									->with("centrocostos")
									->with("monedas")
									->with("clientes")
									->with("localidades")
									->with("provincias")
									->with("paises")
									->with("formapagos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return($ordenventa);
	}

    public function findOrFail($id)
    {
        if (null == $ordenventa = $this->model->with("ordenventa_estados")
									->with("ordenventa_cuotas")
									->with("ordenventa_archivos")
									->with("empresas")
									->with("centrocostos")
									->with("monedas")
									->with("clientes")
									->with("localidades")
									->with("provincias")
									->with("paises")
									->with("formapagos")
									->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $ordenventa;
    }

	// Devuelve ultimo numero de ordenventa + 1
	private function ultimaOrdenventa($empresa_id) 
	{
		$ordenventa = $this->model->select('numeroordenventa')->where('empresa_id', $empresa_id)->orderBy('id', 'desc')->first();
		
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

	public function apruebaOrdenventa($ordenventa_id)
	{
		$estado = Ordenventa_Estado::$enumEstado[array_search('P', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];

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
