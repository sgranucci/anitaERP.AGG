<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo_Adelanto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Rendicionreceptivo_AdelantoRepository implements Rendicionreceptivo_AdelantoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo_Adelanto $rendicionreceptivo_adelanto)
    {
        $this->model = $rendicionreceptivo_adelanto;
    }

    public function create(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Adelanto($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$rendicionreceptivo_adelanto = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Adelanto($data, 'update', $id);
    }

    public function delete($rendicionreceptivo_id, $codigo)
    {
        return $this->model->where('rendicionreceptivo_id', $rendicionreceptivo_id)->delete();
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo_adelanto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_adelanto;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo_adelanto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_adelanto;
    }

	private function guardarRendicionreceptivo_Adelanto($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$rendicionreceptivo_adelanto = $this->model->where('rendicionreceptivo_id', $id)->get()->pluck('id')->toArray();
			$q_rendicionreceptivo_adelanto = count($rendicionreceptivo_adelanto);
		}

		// Graba cuentas contables
		if (isset($data['idadelantos']))
		{
			$adelanto_ids = array_unique($data['idadelantos']);

			if ($funcion == 'update')
			{
				$_id = $rendicionreceptivo_adelanto;

				// Borra los que sobran
				if ($q_rendicionreceptivo_adelanto > count($adelanto_ids))
				{
					for ($d = count($adelanto_ids); $d < $q_rendicionreceptivo_adelanto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_rendicionreceptivo_adelanto && $i < count($adelanto_ids); $i++)
				{
					if ($i < count($adelanto_ids))
					{
						$rendicionreceptivo_adelanto = $this->model->findOrFail($_id[$i])->update([
									"rendicionreceptivo_id" => $id,
									"caja_movimiento_id" => $adelanto_ids[$i]
									]);
					}
				}
				if ($q_rendicionreceptivo_adelanto > count($adelanto_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($adelanto_ids); $i_movimiento++)
			{
				if ($adelanto_ids[$i_movimiento] != '') 
				{
					$rendicionreceptivo_adelanto = $this->model->create([
						"rendicionreceptivo_id" => $id,
						"caja_movimiento_id" => $adelanto_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$rendicionreceptivo_adelanto = $this->model->where('rendicionreceptivo_id', $id)->delete();
		}

		return $rendicionreceptivo_adelanto;
	}
}
