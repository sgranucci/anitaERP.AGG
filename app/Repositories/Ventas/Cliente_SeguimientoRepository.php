<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Seguimiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_SeguimientoRepository implements Cliente_SeguimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Seguimiento $cliente_seguimiento)
    {
        $this->model = $cliente_seguimiento;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Seguimiento($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_seguimiento = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Seguimiento($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_seguimiento = $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_seguimiento = $this->model->with('clientes')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_seguimiento;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_seguimiento = $this->model->with('clientes')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_seguimiento;
    }

	private function guardarCliente_Seguimiento($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_seguimiento = $this->model->where('cliente_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_seguimiento = count($cliente_seguimiento);
		}

		// Graba premios
		if (isset($data['fechas']))
		{
			$fechas = $data['fechas'];
			$observaciones = $data['observaciones'];
			$leyendas = $data['leyendas'];
			$creousuario_ids = $data['creousuario_id'];

			if ($funcion == 'update')
			{
				$_id = $cliente_seguimiento;

				// Borra los que sobran
				if ($q_cliente_seguimiento > count($fechas))
				{
					for ($d = count($fechas); $d < $q_cliente_seguimiento; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cliente_seguimiento && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$cliente_seguimiento = $this->model->findOrFail($_id[$i])->update([
									"cliente_id" => $id,
									"fecha" => $fechas[$i],
									"observacion" => $observaciones[$i],
									"leyenda" => $leyendas[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_cliente_seguimiento > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$cliente_seguimiento = $this->model->create([
						"cliente_id" => $id,
						"fecha" => $fechas[$i_movimiento],
						"observacion" => $observaciones[$i_movimiento],
						"leyenda" => $leyendas[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$cliente_seguimiento = $this->model->where('cliente_id', $id)->delete();
		}

		return $cliente_seguimiento;
	}

}
