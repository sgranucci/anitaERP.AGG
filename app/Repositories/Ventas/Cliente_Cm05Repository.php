<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Cliente_Cm05;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cliente_Cm05Repository implements Cliente_Cm05RepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Cm05 $cliente_cm05)
    {
        $this->model = $cliente_cm05;
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Cm05($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cliente_cm05 = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCliente_Cm05($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_cm05 = $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_cm05 = $this->model->with('clientes')->with('provincias')->with('creousuarios')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cm05;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_cm05 = $this->model->with('clientes')->with('provincias')->with('creousuarios')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_cm05;
    }

	private function guardarCliente_Cm05($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_cm05 = $this->model->where('cliente_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_cm05 = count($cliente_cm05);
		}

		// Graba premios
		if (isset($data['provincia_ids']))
		{
			$provincia_ids = $data['provincia_ids'];
			$tipopercepciones = $data['tipopercepciones'];
			$coeficientes = $data['coeficientes'];
			$fechavigencias = $data['fechavigencias'];
			$certificadonoretenciones = $data['certificadonoretenciones'];
			$desdefechanoretenciones = $data['desdefechanoretenciones'];
			$hastafechanoretenciones = $data['hastafechanoretenciones'];
			$creousuario_ids = $data['creousuario_cm05_ids'];

			if ($funcion == 'update')
			{
				$_id = $cliente_cm05;

				// Borra los que sobran
				if ($q_cliente_cm05 > count($provincia_ids))
				{
					for ($d = count($provincia_ids); $d < $q_cliente_cm05; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cliente_cm05 && $i < count($provincia_ids); $i++)
				{
					if ($i < count($provincia_ids))
					{
						$cliente_cm05 = $this->model->findOrFail($_id[$i])->update([
									"cliente_id" => $id,
									"provincia_id" => $provincia_ids[$i],
									"tipopercepcion" => $tipopercepciones[$i],
									"coeficiente" => $coeficientes[$i],
									"fechavigencia" => $fechavigencias[$i],
									"certificadonoretencion" => $certificadonoretenciones[$i],
									"desdefechanoretencion" => $desdefechanoretenciones[$i],
									"hastafechanoretencion" => $hastafechanoretenciones[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_cliente_cm05 > count($provincia_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($provincia_ids); $i_movimiento++)
			{
				if ($provincia_ids[$i_movimiento] != '') 
				{
					$cliente_cm05 = $this->model->create([
						"cliente_id" => $id,
						"provincia_id" => $provincia_ids[$i_movimiento],
						"tipopercepcion" => $tipopercepciones[$i_movimiento],
						"coeficiente" => $coeficientes[$i_movimiento],
						"fechavigencia" => $fechavigencias[$i_movimiento],
						"certificadonoretencion" => $certificadonoretenciones[$i_movimiento],
						"desdefechanoretencion" => $desdefechanoretenciones[$i_movimiento],
						"hastafechanoretencion" => $hastafechanoretenciones[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$cliente_cm05 = $this->model->where('cliente_id', $id)->delete();
		}

		return $cliente_cm05;
	}

}
