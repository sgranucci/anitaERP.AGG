<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex_Estado;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Capex_EstadoRepository implements Capex_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Capex_Estado $capex_estado)
    {
        $this->model = $capex_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarCapex_Estado($data, 'create', $id);
    }

	public function creaEstado($id, $fecha, $estado, $usuario_id, $observacion)
	{
		Self::createUnique(["capex_id" => $id,
						"fecha" => $fecha,
						"estado" => $estado,
						"usuario_id" => $usuario_id,
						"observacion" => $observacion]);
	}

	public function createUnique(array $data)
	{
		$capex_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCapex_Estado($data, 'update', $id);
    }

    public function delete($capex_id, $codigo)
    {
        return EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('capex_id', $capex_id)
        );
    }

    public function find($id)
    {
        if (null == $capex_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_estado;
    }

    public function findOrFail($id)
    {
        if (null == $capex_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $capex_estado;
    }

	private function guardarCapex_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$capex_estado = $this->model->where('capex_id', $id)->get()->pluck('id')->toArray();
			$q_capex_estado = count($capex_estado);
		}

		// Graba estados
		if (isset($data))
		{
			$fechas = $data['fechas'];
			$estados = $data['estados'];
			$usuario_ids = $data['usuario_ids'];
			$observaciones = $data['observacionestados'];

			if ($funcion == 'update')
			{
				$_id = $capex_estado;

				// Borra los que sobran
				if ($q_capex_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_capex_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_capex_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$capex_estado = $this->model->findOrFail($_id[$i])->update([
									"capex_id" => $id,
									"fecha" => $fechas[$i],
									"estado" => $estados[$i],
									"usuario_id" => $usuario_ids[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_capex_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$capex_estado = $this->model->create([
						"capex_id" => $id,
						"fecha" => $fechas[$i_movimiento],
						"estado" => $estados[$i_movimiento],
						"usuario_id" => $usuario_ids[$i],
						"observacion" => $observaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$capex_estado = EloquentAuditDeleteSupport::each(
				$this->model->newQuery()->where('capex_id', $id)
			);
		}

		return $capex_estado;
	}

	public function leeHistoriaCapex($capex_id)
	{
		return $this->model->select('id',
							'capex_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion')
					->where('capex_id', $capex_id)
					->with('usuarios')
					->get();
	}

}
