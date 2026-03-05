<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Movimiento_Estado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Caja_Movimiento_EstadoRepository implements Caja_Movimiento_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Movimiento_Estado $caja_movimiento_estado)
    {
        $this->model = $caja_movimiento_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarCaja_Movimiento_Estado($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$caja_movimiento_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCaja_Movimiento_Estado($data, 'update', $id);
    }

    public function delete($caja_movimiento_id, $codigo)
    {
        return $this->model->where('caja_movimiento_id', $caja_movimiento_id)->delete();
    }

    public function find($id)
    {
        if (null == $caja_movimiento_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_estado;
    }

    public function findOrFail($id)
    {
        if (null == $caja_movimiento_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_estado;
    }

	private function guardarCaja_Movimiento_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$caja_movimiento_estado = $this->model->where('caja_movimiento_id', $id)->get()->pluck('id')->toArray();
			$q_caja_movimiento_estado = count($caja_movimiento_estado);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$fechas = $data['fechas'];
			$estados = $data['estados'];
			$observaciones = $data['observacionestados'];

			if ($funcion == 'update')
			{
				$_id = $caja_movimiento_estado;

				// Borra los que sobran
				if ($q_caja_movimiento_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_caja_movimiento_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_caja_movimiento_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$caja_movimiento_estado = $this->model->findOrFail($_id[$i])->update([
									"caja_movimiento_id" => $id,
									"fecha" => $fechas[$i],
									"estado" => $estados[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_caja_movimiento_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$caja_movimiento_estado = $this->model->create([
						"caja_movimiento_id" => $id,
						"fecha" => $fechas[$i_movimiento],
						"estado" => $estados[$i_movimiento],
						"observacion" => $observaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$caja_movimiento_estado = $this->model->where('caja_movimiento_id', $id)->delete();
		}

		return $caja_movimiento_estado;
	}
}
