<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto_Monto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Partidagasto_MontoRepository implements Partidagasto_MontoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Partidagasto_Monto $partidagasto_monto)
    {
        $this->model = $partidagasto_monto;
    }

    public function create(array $data, $id)
    {
		return self::guardarPartidagasto_Monto($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		return $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarPartidagasto_Monto($data, 'update', $id);
    }

    public function delete($capex_id)
    {
        return $this->model->where('capex_id', $capex_id)->delete();
    }

    public function find($id)
    {
        if (null == $partidagasto_monto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_monto;
    }

    public function findOrFail($id)
    {
        if (null == $partidagasto_monto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_monto;
    }

	public function findPorPartidagasto($partidagasto_id)
	{
		return $this->model->where('partidagasto_id', $partidagasto_id)->get();
	}
	
	private function guardarPartidagasto_Monto($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$partidagasto_monto = $this->model->where('partidagasto_id', $data['partidagasto_id'])->get()->pluck('id')->toArray();
			$q_partidagasto_monto = count($partidagasto_monto);
		}
		// Graba estados
		if (isset($data))
		{
			if (isset($data['periodos']))
			{
				$periodos = $data['periodos'];
				$montos = $data['montos'];
				$creousuario_ids = $data['creousuario_ids'];
			}
			else
			{
				$periodos = [];
				$montos = [];
				$creousuario_ids = [];
			}

			if ($funcion == 'update')
			{
				$_id = $partidagasto_monto;

				// Borra los que sobran
				if ($q_partidagasto_monto > count($periodos))
				{
					for ($d = count($periodos); $d < $q_partidagasto_monto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_partidagasto_monto && $i < count($periodos); $i++)
				{
					if ($i < count($periodos))
					{
						$partidagasto_monto = $this->model->findOrFail($_id[$i])->update([
									"partidagasto_id" => $id,
									"periodo" => $periodos[$i],
									"monto" => $montos[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_partidagasto_monto > count($periodos))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($periodos); $i_movimiento++)
			{
				if ($periodos[$i_movimiento] != '') 
				{
					$partidagasto_monto = $this->model->create([
						"partidagasto_id" => $id,
						"periodo" => $periodos[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$partidagasto_monto = $this->model->where('partidagasto_id', $id)->delete();
		}

		return $partidagasto_monto;
	}
}
