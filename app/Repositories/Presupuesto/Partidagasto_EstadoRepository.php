<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto_Estado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Partidagasto_EstadoRepository implements Partidagasto_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Partidagasto_Estado $partidagasto_estado)
    {
        $this->model = $partidagasto_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarPartidagasto_Estado($data, 'create', $id);
    }

	public function creaEstado($id, $fecha, $estado, $usuario_id, $observacion)
	{
		Self::createUnique(["partidagasto_id" => $id,
						"fecha" => $fecha,
						"estado" => $estado,
						"usuario_id" => $usuario_id,
						"observacion" => $observacion]);
	}

	public function createUnique(array $data)
	{
		$partidagasto_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarPartidagasto_Estado($data, 'update', $id);
    }

    public function delete($partidagasto_id, $codigo)
    {
        return $this->model->where('partidagasto_id', $partidagasto_id)->delete();
    }

    public function find($id)
    {
        if (null == $partidagasto_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_estado;
    }

    public function findOrFail($id)
    {
        if (null == $partidagasto_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $partidagasto_estado;
    }

	private function guardarPartidagasto_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$partidagasto_estado = $this->model->where('partidagasto_id', $id)->get()->pluck('id')->toArray();
			$q_partidagasto_estado = count($partidagasto_estado);
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
				$_id = $partidagasto_estado;

				// Borra los que sobran
				if ($q_partidagasto_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_partidagasto_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_partidagasto_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$partidagasto_estado = $this->model->findOrFail($_id[$i])->update([
									"partidagasto_id" => $id,
									"fecha" => $fechas[$i],
									"estado" => $estados[$i],
									"usuario_id" => $usuario_ids[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_partidagasto_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$partidagasto_estado = $this->model->create([
						"partidagasto_id" => $id,
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
			$partidagasto_estado = $this->model->where('partidagasto_id', $id)->delete();
		}

		return $partidagasto_estado;
	}

	public function leeHistoriaPartidagasto($partidagasto_id)
	{
		return $this->model->select('id',
							'partidagasto_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion')
					->where('partidagasto_id', $partidagasto_id)
					->where('deleted_at', null)
					->with('usuarios')
					->get();
	}

}
