<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Ordenventa_Concepto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ordenventa_ConceptoRepository implements Ordenventa_ConceptoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenventa_Concepto $ordenventa_concepto)
    {
        $this->model = $ordenventa_concepto;
    }

    public function create(array $data, $id)
    {
		return self::guardarOrdenventa_Concepto($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$ordenventa_concepto = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarOrdenventa_Concepto($data, 'update', $id);
    }

    public function delete($ordenventa_id, $codigo)
    {
        return $this->model->where('ordenventa_id', $ordenventa_id)->delete();
    }

    public function find($id)
    {
        if (null == $ordenventa_concepto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_concepto;
    }

    public function findOrFail($id)
    {
        if (null == $ordenventa_concepto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_concepto;
    }

	public function findPorOrdenventa($ordenventa_id)
	{
		return $this->model->where('ordenventa_id', $ordenventa_id)->get();
	}
	
	private function guardarOrdenventa_Concepto($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ordenventa_concepto = $this->model->where('ordenventa_id', $id)->get()->pluck('id')->toArray();
			$q_ordenventa_concepto = count($ordenventa_concepto);
		}

		// Graba estados
		if (isset($data))
		{
			if (isset($data['montoconceptos']))
			{
				$concepto_ordenventa_ids = $data['concepto_ordenventa_ids'];
				$cantidadconceptos = $data['cantidadconceptos'];
				$montoconceptos = $data['montoconceptos'];
				$detalles = $data['detalleconceptos'];
			}
			else
			{
				$concepto_ordenventa_ids = [];
				$montoconceptos = [];
				$cantidadconceptos = [];
				$detalles = [];
			}

			if ($funcion == 'update')
			{
				$_id = $ordenventa_concepto;

				// Borra los que sobran
				if ($q_ordenventa_concepto > count($montoconceptos))
				{
					for ($d = count($montoconceptos); $d < $q_ordenventa_concepto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ordenventa_concepto && $i < count($montoconceptos); $i++)
				{
					if ($i < count($montoconceptos))
					{
						$ordenventa_concepto = $this->model->findOrFail($_id[$i])->update([
									"ordenventa_id" => $id,
									"concepto_ordenventa_id" => $concepto_ordenventa_ids[$i],
									"cantidad" => $cantidadconceptos[$i],
									"detalle" => $detalles[$i],
									"monto" => $montoconceptos[$i]
									]);
					}
				}
				if ($q_ordenventa_concepto > count($montoconceptos))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($montoconceptos); $i_movimiento++)
			{
				if ($montoconceptos[$i_movimiento] != 0) 
				{
					$ordenventa_concepto = $this->model->create([
							"ordenventa_id" => $id,
							"concepto_ordenventa_id" => $concepto_ordenventa_ids[$i_movimiento],
							"cantidad" => $cantidadconceptos[$i_movimiento],
							"detalle" => $detalles[$i_movimiento],
							"monto" => $montoconceptos[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$ordenventa_concepto = $this->model->where('ordenventa_id', $id)->delete();
		}

		return $ordenventa_concepto;
	}
}
