<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Ordenventa_Estado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ordenventa_EstadoRepository implements Ordenventa_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenventa_Estado $ordenventa_estado)
    {
        $this->model = $ordenventa_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarOrdenventa_Estado($data, 'create', $id);
    }

	public function creaEstado($id, $fecha, $estado, $usuario_id, $observacion)
	{
		Self::createUnique(["ordenventa_id" => $id,
						"fecha" => $fecha,
						"estado" => $estado,
						"usuario_id" => $usuario_id,
						"observacion" => $observacion]);
	}

	public function createUnique(array $data)
	{
		$ordenventa_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarOrdenventa_Estado($data, 'update', $id);
    }

    public function delete($ordenventa_id, $codigo)
    {
        return $this->model->where('ordenventa_id', $ordenventa_id)->delete();
    }

    public function find($id)
    {
        if (null == $ordenventa_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_estado;
    }

    public function findOrFail($id)
    {
        if (null == $ordenventa_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_estado;
    }

	private function guardarOrdenventa_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ordenventa_estado = $this->model->where('ordenventa_id', $id)->get()->pluck('id')->toArray();
			$q_ordenventa_estado = count($ordenventa_estado);
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
				$_id = $ordenventa_estado;

				// Borra los que sobran
				if ($q_ordenventa_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_ordenventa_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ordenventa_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$ordenventa_estado = $this->model->findOrFail($_id[$i])->update([
									"ordenventa_id" => $id,
									"fecha" => $fechas[$i],
									"estado" => $estados[$i],
									"usuario_id" => $usuario_ids[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_ordenventa_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$ordenventa_estado = $this->model->create([
						"ordenventa_id" => $id,
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
			$ordenventa_estado = $this->model->where('ordenventa_id', $id)->delete();
		}

		return $ordenventa_estado;
	}

	public function leeHistoriaOrdenventa($ordenventa_id)
	{
		return $this->model->select('id',
							'ordenventa_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion')
					->where('ordenventa_id', $ordenventa_id)
					->where('deleted_at', null)
					->with('usuarios')
					->get();
	}

}
