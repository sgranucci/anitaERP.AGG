<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex_Partida_Monto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Capex_Partida_MontoRepository implements Capex_Partida_MontoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Capex_Partida_Monto $capex_partida_monto)
    {
        $this->model = $capex_partida_monto;
    }

    public function create(array $data)
    {
		return self::guardarCapex_Partida_Monto($data, 'create');
    }

	public function createUnique(array $data)
	{
		return $this->model->create($data);
	}

    public function update(array $data)
    {
		return self::guardarCapex_Partida_Monto($data, 'update');
    }

    public function delete($capex_id)
    {
        return $this->model->where('capex_id', $capex_id)->delete();
    }

    public function find($id)
    {
        if (null == $capex_partida_monto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_partida_monto;
    }

    public function findOrFail($id)
    {
        if (null == $capex_partida_monto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_partida_monto;
    }

	public function findPorCapex_Partida($capex_partida_id)
	{
		return $this->model->where('capex_partida_id', $capex_partida_id)->get();
	}
	
	private function guardarCapex_Partida_Monto($data, $funcion)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$capex_partida_monto = $this->model->where('capex_partida_id', $data['capex_partida_ids'][0])->get()->pluck('id')->toArray();
			$q_capex_partida_monto = count($capex_partida_monto);
		}
		// Graba estados
		if (isset($data))
		{
			if (isset($data['periodos']))
			{
				$capex_partida_ids = $data['capex_partida_ids'];
				$capex_ids = $data['capex_ids'];
				$periodos = $data['periodos'];
				$montos = $data['montos'];
				$creousuario_ids = $data['creousuario_ids'];
			}
			else
			{
				$capex_partida_ids = [];
				$capex_ids = [];
				$periodos = [];
				$montos = [];
				$creousuario_ids = [];
			}

			if ($funcion == 'update')
			{
				$_id = $capex_partida_monto;

				// Borra los que sobran
				if ($q_capex_partida_monto > count($periodos))
				{
					for ($d = count($periodos); $d < $q_capex_partida_monto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_capex_partida_monto && $i < count($periodos); $i++)
				{
					if ($i < count($periodos))
					{
						$capex_partida_monto = $this->model->findOrFail($_id[$i])->update([
									"capex_partida_id" => $capex_partida_ids[$i],
									"capex_id" => $capex_ids[$i],
									"periodo" => $periodos[$i],
									"monto" => $montos[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_capex_partida_monto > count($periodos))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($periodos); $i_movimiento++)
			{
				if ($periodos[$i_movimiento] != '') 
				{
					$capex_partida_monto = $this->model->create([
						"capex_partida_id" => $capex_partida_ids[$i_movimiento],
						"capex_id" => $capex_ids[$i_movimiento],
						"periodo" => $periodos[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$capex_partida_monto = $this->model->where('capex_partida_id', $data['capex_partida_ids'][0])->delete();
		}

		return $capex_partida_monto;
	}
}
