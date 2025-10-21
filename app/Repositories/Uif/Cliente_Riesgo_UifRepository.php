<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Riesgo_Uif;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_Riesgo_UifRepository implements Cliente_Riesgo_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Riesgo_Uif $cliente_riesgo_uif)
    {
        $this->model = $cliente_riesgo_uif;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Riesgo_Uif($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_riesgo_uif = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Riesgo_Uif($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_riesgo_uif = $this->model->findOrFail($id)->update($data);
    }

    public function delete($cliente_uif_id)
    {
        return $this->model->where('cliente_uif_id', $cliente_uif_id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_riesgo_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_riesgo_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_riesgo_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_riesgo_uif;
    }

	private function guardarCliente_Riesgo_Uif($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$idActuales = $this->model->where('cliente_uif_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_riesgo_uif = count($idActuales);
		}

		// Graba riesgos
		if (isset($data['riesgo_ids']))
		{
			$riesgo_ids = $data['riesgo_ids'];
			$periodos = $data['periodos'];
			$inusualidad_uif_ids = $data['inusualidad_uif_ids'];
			$riesgos = $data['riesgos'];
			$creousuario_ids = $data['creousuario_riesgo_ids'];

			for ($i = 0; $i < count($riesgo_ids); $i++)
			{
				$cliente_riesgo_uif = $this->model->find($riesgo_ids[$i]);

				if ($cliente_riesgo_uif)
				{
					$cliente_riesgo_uif->update([
								"cliente_uif_id" => $id,
								"periodo" => $periodos[$i],
								"inusualidad_uif_id" => $inusualidad_uif_ids[$i],
								"riesgo" => $riesgos[$i],
								"creousuario_id" => $creousuario_ids[$i]
								]);					
				}
				else
				{
					if ($riesgo_ids[$i] != '') 
					{
						$cliente_riesgo_uif = $this->model->create([
							"cliente_uif_id" => $id,
							"periodo" => $periodos[$i],
							"inusualidad_uif_id" => $inusualidad_uif_ids[$i],
							"riesgo" => $riesgos[$i],
							"creousuario_id" => $creousuario_ids[$i]						
							]);
						
						$riesgo_ids[$i] = $cliente_riesgo_uif->id;
					}
				}
			}

			// Borra los eliminados que no estan en la lista $riesgo_ids
			if ($funcion == 'update')
			{
				for ($i = 0; $i < $q_cliente_riesgo_uif; $i++)
				{
					// Lo busca en la tabla de ids nueva
					for ($j = 0, $flEncontro = false; $j < count($riesgo_ids); $j++)
					{
						if ($idActuales[$i] == $riesgo_ids[$j])
							$flEncontro = true;
					}
					if (!$flEncontro)
						$this->model->find($idActuales[$i])->delete();
				}
			}
		}
		else
		{
			$cliente_riesgo_uif = $this->model->where('cliente_uif_id', $id)->delete();
		}

		return $cliente_riesgo_uif;
	}
}
