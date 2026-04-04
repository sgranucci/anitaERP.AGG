<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Encuesta_Pregunta;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Encuesta_PreguntaRepository implements Encuesta_PreguntaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Encuesta_Pregunta $encuesta_pregunta)
    {
        $this->model = $encuesta_pregunta;
    }

    public function create(array $data, $id)
    {
		return self::guardarEncuesta_Pregunta($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$encuesta_pregunta = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarEncuesta_Pregunta($data, 'update', $id);
    }

    public function delete($encuesta_id)
    {
        return $this->model->where('encuesta_id', $encuesta_id)->delete();
    }

    public function find($id)
    {
        if (null == $encuesta_pregunta = $this->model->with('empresas')->with('centrocostos')->with('monedas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $encuesta_pregunta;
    }

    public function findOrFail($id)
    {
        if (null == $encuesta_pregunta = $this->model->with('empresas')->with('centrocostos')->with('monedas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $encuesta_pregunta;
    }

	private function guardarEncuesta_Pregunta($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$encuesta_pregunta = $this->model->where('encuesta_id', $id)->get()->pluck('id')->toArray();
			$q_encuesta_pregunta = count($encuesta_pregunta);
		}

		// Graba preguntas
		if (isset($data))
		{
			$nombres = $data['nombres'];
			$desdepuntajes = $data['desdepuntajes'];
			$hastapuntajes = $data['hastapuntajes'];
			
			if ($funcion == 'update')
			{
				$_id = $encuesta_pregunta;

				// Borra los que sobran
				if ($q_encuesta_pregunta > count($nombres))
				{
					for ($d = count($nombres); $d < $q_encuesta_pregunta; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_encuesta_pregunta && $i < count($nombres); $i++)
				{
					if ($i < count($nombres))
					{
						$encuesta_pregunta = $this->model->findOrFail($_id[$i])->update([
									'encuesta_id' => $id,
									'nombre' => $nombres[$i],
									'desdepuntaje' => $desdepuntajes[$i],
									'hastapuntaje' => $hastapuntajes[$i]
									]);
					}
				}
				if ($q_encuesta_pregunta > count($nombres))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($nombres); $i_movimiento++)
			{
				if ($nombres[$i_movimiento] != '') 
				{
					$encuesta_pregunta = $this->model->create([
							'encuesta_id' => $id,
							'nombre' => $nombres[$i_movimiento],
							'desdepuntaje' => $desdepuntajes[$i_movimiento], 
							'hastapuntaje' => $hastapuntajes[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$encuesta_pregunta = $this->model->where('encuesta_id', $id)->delete();
		}

		return $encuesta_pregunta;
	}

}
