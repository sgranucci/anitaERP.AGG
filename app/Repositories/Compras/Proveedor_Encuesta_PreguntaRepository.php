<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Encuesta_Pregunta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Proveedor_Encuesta_PreguntaRepository implements Proveedor_Encuesta_PreguntaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Encuesta_Pregunta $proveedor_encuesta_pregunta)
    {
        $this->model = $proveedor_encuesta_pregunta;
    }

    public function create(array $data, $id)
    {
		return self::guardarProveedor_Encuesta_Pregunta($data, 'create', $id);
    }

    public function createUnique(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarProveedor_Encuesta_Pregunta($data, 'update', $id);
    }

    public function delete($id)
    {
        $proveedor_encuesta_pregunta = $this->model->where('id', $id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

	public function leePorProveedorEncuesta_Pregunta($proveedor_encuesta_id)
	{
		$proveedor_encuesta_pregunta = $this->model->where('proveedor_encuesta_id', $proveedor_encuesta_id)->get();

		return $proveedor_encuesta_pregunta;
	}
	
    public function findOrFail($id)
    {
        if (null == $proveedor_encuesta_pregunta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarProveedor_Encuesta_Pregunta($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$proveedor_encuesta_pregunta = $this->model->where('proveedor_encuesta_id', $id)->get()->pluck('id')->toArray();
			$q_proveedor_encuesta_pregunta = count($proveedor_encuesta_pregunta);
		}

		// Graba formas de pago
		if (isset($data['encuesta_pregunta_ids']))
		{
			$encuesta_pregunta_ids = $data['encuesta_pregunta_ids'];

			if ($funcion == 'update')
			{
				$_id = $proveedor_encuesta_pregunta;

				// Borra las que sobran
				if ($q_proveedor_encuesta_pregunta > count($encuesta_pregunta_ids))
				{
					for ($d = count($encuesta_pregunta_ids); $d < $q_proveedor_encuesta_pregunta; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_proveedor_encuesta_pregunta && $i < count($encuesta_pregunta_ids); $i++)
				{
					if ($i < count($encuesta_pregunta_ids))
					{
						$puntaje = $data['puntaje'.($i+1)][0];

						$proveedor_encuesta_pregunta = $this->model->findOrFail($_id[$i])->update([
									"proveedor_id" => $data['proveedor_id'],
									"proveedor_encuesta_id" => $id,
									"encuesta_id" => $data['encuesta_id'],
									"encuesta_pregunta_id" => $encuesta_pregunta_ids[$i],
									"puntaje" => $puntaje
									]);
					}
				}
				if ($q_proveedor_encuesta_pregunta > count($encuesta_pregunta_ids))
					$i = $d; 
			}
			else
				$i = 0;

			// Agrega el resto de las preguntas de la encuesta
			for ($i_encuesta_pregunta = $i; $i_encuesta_pregunta < count($encuesta_pregunta_ids); $i_encuesta_pregunta++)
			{
				//* Valida si se cargo una formapago
				if ($encuesta_pregunta_ids[$i_encuesta_pregunta] != '') 
				{
					$puntaje = $data['puntaje'.($i_encuesta_pregunta+1)][0];

					$proveedor_encuesta_pregunta = $this->model->create([
										"proveedor_id" => $data['proveedor_id'],
										"proveedor_encuesta_id" => $id,
										"encuesta_id" => $data['encuesta_id'],
										"encuesta_pregunta_id" => $encuesta_pregunta_ids[$i_encuesta_pregunta],
										"puntaje" => $puntaje
									]);
				}
			}
		}
		else // Borra todas las preguntas de la encuesta
		{
			$proveedor_encuesta_pregunta = $this->model->where('proveedor_encuesta_id', $id)->delete();
		}
	}
}
