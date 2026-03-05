<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo_Caja_Movimiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Rendicionreceptivo_Caja_MovimientoRepository implements Rendicionreceptivo_Caja_MovimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo_Caja_Movimiento $rendicionreceptivo_caja_movimiento)
    {
        $this->model = $rendicionreceptivo_caja_movimiento;
    }

    public function create(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Caja_Movimiento($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$rendicionreceptivo_caja_movimiento = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Caja_Movimiento($data, 'update', $id);
    }

    public function delete($rendicionreceptivo_id, $codigo)
    {
        return $this->model->where('rendicionreceptivo_id', $rendicionreceptivo_id)->delete();
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo_caja_movimiento = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_caja_movimiento;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo_caja_movimiento = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_caja_movimiento;
    }

	private function guardarRendicionreceptivo_Caja_Movimiento($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$rendicionreceptivo_caja_movimiento = $this->model->where('rendicionreceptivo_id', $id)->get()->pluck('id')->toArray();
			$q_rendicionreceptivo_caja_movimiento = count($rendicionreceptivo_caja_movimiento);
		}

		// Graba cuentas contables
		if (isset($data['idgastoanteriores']))
		{
			$caja_movimiento_gastoanterior_ids = array_unique($data['idgastoanteriores']);

			if ($funcion == 'update')
			{
				$_id = $rendicionreceptivo_caja_movimiento;

				// Borra los que sobran
				if ($q_rendicionreceptivo_caja_movimiento > count($caja_movimiento_gastoanterior_ids))
				{
					for ($d = count($caja_movimiento_gastoanterior_ids); $d < $q_rendicionreceptivo_caja_movimiento; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_rendicionreceptivo_caja_movimiento && $i < count($caja_movimiento_gastoanterior_ids); $i++)
				{
					if ($i < count($caja_movimiento_gastoanterior_ids))
					{
						$rendicionreceptivo_caja_movimiento = $this->model->findOrFail($_id[$i])->update([
									"rendicionreceptivo_id" => $id,
									"caja_movimiento_id" => $caja_movimiento_gastoanterior_ids[$i]
									]);
					}
				}
				if ($q_rendicionreceptivo_caja_movimiento > count($caja_movimiento_gastoanterior_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($caja_movimiento_gastoanterior_ids); $i_movimiento++)
			{
				if ($caja_movimiento_gastoanterior_ids[$i_movimiento] != '') 
				{
					$rendicionreceptivo_caja_movimiento = $this->model->create([
						"rendicionreceptivo_id" => $id,
						"caja_movimiento_id" => $caja_movimiento_gastoanterior_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$rendicionreceptivo_caja_movimiento = $this->model->where('rendicionreceptivo_id', $id)->delete();
		}

		return $rendicionreceptivo_caja_movimiento;
	}
}
