<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_Nivel;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Arbolaprobacion_NivelRepository implements Arbolaprobacion_NivelRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Arbolaprobacion_Nivel $arbolaprobacion_nivel)
    {
        $this->model = $arbolaprobacion_nivel;
    }

    public function create(array $data, $id)
    {
		return self::guardarArbolaprobacion_Nivel($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$arbolaprobacion_nivel = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarArbolaprobacion_Nivel($data, 'update', $id);
    }

    public function delete($arbolaprobacion_id)
    {
        return $this->model->where('arbolaprobacion_id', $arbolaprobacion_id)->delete();
    }

    public function find($id)
    {
        if (null == $arbolaprobacion_nivel = $this->model->with('empresas')->with('centrocostos')->with('monedas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $arbolaprobacion_nivel;
    }

    public function findOrFail($id)
    {
        if (null == $arbolaprobacion_nivel = $this->model->with('empresas')->with('centrocostos')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $arbolaprobacion_nivel;
    }

	private function guardarArbolaprobacion_Nivel($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$arbolaprobacion_nivel = $this->model->where('arbolaprobacion_id', $id)->get()->pluck('id')->toArray();
			$q_arbolaprobacion_nivel = count($arbolaprobacion_nivel);
		}

		// Graba niveles del arbol de aprobacion
		if (isset($data))
		{
			$niveles = $data['niveles'];
			$centrocosto_ids = $data['centrocosto_ids'];
			$desdemontos = $data['desdemontos'];
			$hastamontos = $data['hastamontos'];
			$moneda_ids = $data['moneda_ids'];
			$usuario_ids = $data['usuario_ids'];
			
			if ($funcion == 'update')
			{
				$_id = $arbolaprobacion_nivel;

				// Borra los que sobran
				if ($q_arbolaprobacion_nivel > count($usuario_ids))
				{
					for ($d = count($usuario_ids); $d < $q_arbolaprobacion_nivel; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_arbolaprobacion_nivel && $i < count($usuario_ids); $i++)
				{
					if ($i < count($usuario_ids))
					{
						$arbolaprobacion_nivel = $this->model->findOrFail($_id[$i])->update([
									'arbolaprobacion_id' => $id,
									'nivel' => $niveles[$i],
									'centrocosto_id' => $centrocosto_ids[$i], 
									'usuario_id' => $usuario_ids[$i],
									'desdemonto' => $desdemontos[$i],
									'hastamonto' => $hastamontos[$i],
									'moneda_id' => $moneda_ids[$i]
									]);
					}
				}
				if ($q_arbolaprobacion_nivel > count($usuario_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($usuario_ids); $i_movimiento++)
			{
				if ($usuario_ids[$i_movimiento] != '') 
				{
					$arbolaprobacion_nivel = $this->model->create([
							'arbolaprobacion_id' => $id,
							'nivel' => $niveles[$i_movimiento],
							'centrocosto_id' => $centrocosto_ids[$i_movimiento], 
							'usuario_id' => $usuario_ids[$i_movimiento],
							'desdemonto' => $desdemontos[$i_movimiento], 
							'hastamonto' => $hastamontos[$i_movimiento], 
							'moneda_id' => $moneda_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$arbolaprobacion_nivel = $this->model->where('arbolaprobacion_id', $id)->delete();
		}

		return $arbolaprobacion_nivel;
	}

}
