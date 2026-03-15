<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex_Partida;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Capex_PartidaRepository implements Capex_PartidaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Capex_Partida $capex_partida)
    {
        $this->model = $capex_partida;
    }

    public function create(array &$data, $id)
    {
		return self::guardarCapex_Partida($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		return $this->model->create($data);
	}

    public function update(array &$data, $id)
    {
		return self::guardarCapex_Partida($data, 'update', $id);
    }

    public function delete($capex_id, $codigo)
    {
        return $this->model->where('capex_id', $capex_id)->delete();
    }

    public function find($id)
    {
        if (null == $capex_partida = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_partida;
    }

    public function findOrFail($id)
    {
        if (null == $capex_partida = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_partida;
    }

	public function findPorCapex($capex_id)
	{
		return $this->model->where('capex_id', $capex_id)->get();
	}
	
	private function guardarCapex_Partida(&$data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$capex_partida = $this->model->where('capex_id', $id)->get()->pluck('id')->toArray();
			$q_capex_partida = count($capex_partida);
		}

		// Graba estados
		if (isset($data))
		{
			if (isset($data['moneda_ids']))
			{
				$nombres = $data['nombres'];
				$proveedor_ids = $data['proveedor_ids'];
				$moneda_ids = $data['moneda_ids'];
				$estados = [$data['estado']];
				$codigos = $data['codigos'];
				$creousuario_ids = $data['creousuario_ids'];
			}
			else
			{
				$nombres = [];
				$proveedor_ids = [];
				$moneda_ids = [];
				$estados = [];
				$codigos = [];
				$creousuario_ids = [];
			}

			if ($funcion == 'update')
			{
				$_id = $capex_partida;

				// Borra los que sobran
				if ($q_capex_partida > count($moneda_ids))
				{
					for ($d = count($moneda_ids); $d < $q_capex_partida; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_capex_partida && $i < count($moneda_ids); $i++)
				{
					if ($i < count($moneda_ids))
					{
						// Si no existe el codigo lo tiene que crear
						if ($codigos[$i] == null)
							$codigos[$i] = Self::ultimoCodigo();

						$capex_partida = $this->model->findOrFail($_id[$i])->update([
									"capex_id" => $id,
									"nombre" => $nombres[$i],
									"proveedor_id" => $proveedor_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"estado" => $estados[$i],
									"codigo" => $codigos[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);

						$data['capex_partida_ids'][$i] = $_id[$i];
						$data['codigos'][$i] = $codigos[$i];
					}
				}
				if ($q_capex_partida > count($moneda_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($moneda_ids); $i_movimiento++)
			{
				if ($moneda_ids[$i_movimiento] != '') 
				{
					// Si no existe el codigo lo tiene que crear
					if ($codigos[$i_movimiento] == null)
						$codigos[$i_movimiento] = Self::ultimoCodigo();

					$capex_partida = $this->model->create([
						"capex_id" => $id,
						"nombre" => $nombres[$i_movimiento],
						"proveedor_id" => $proveedor_ids[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"estado" => $estados[$i_movimiento],
						"codigo" => $codigos[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);

					$data['capex_partida_ids'][$i_movimiento] = $capex_partida->id;	
					$data['codigos'][$i_movimiento] = $codigos[$i_movimiento];					
				}
			}
		}
		else
		{
			$capex_partida = $this->model->where('capex_id', $id)->delete();
		}

		return $capex_partida;
	}
	
	// Devuelve ultimo numero de codigo + 1 (campo numero de partida de capex)
	private function ultimoCodigo()
	{
		$capex_partida = $this->model->select('codigo')->orderBy('id', 'desc')->first();
		
		$numeropartida = 0;
        if ($capex_partida) 
		{
			$numeropartida = $capex_partida->codigo;
			$numeropartida = $numeropartida + 1;
		}
		else	
			$numeropartida = 1;

		return $numeropartida;
	}		
}
