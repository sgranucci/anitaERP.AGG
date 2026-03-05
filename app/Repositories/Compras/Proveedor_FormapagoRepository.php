<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Formapago;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Proveedor_FormapagoRepository implements Proveedor_FormapagoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Formapago $proveedor_formapago)
    {
        $this->model = $proveedor_formapago;
    }

    public function create(array $data, $id)
    {
		return self::guardarProveedor_Formapago($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
		return self::guardarProveedor_Formapago($data, 'update', $id);
    }

    public function delete($proveedor_id, $codigo)
    {
        $proveedor_formapago = $this->model->where('proveedor_id', $proveedor_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        if (null == $proveedor_formapago = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeProveedorFormapago($proveedor_id)
	{
		$proveedor_formapago = $this->model->where('proveedor_id', $proveedor_id)->get();

		return $proveedor_formapago;
	}
	
    public function findOrFail($id)
    {
        if (null == $proveedor_formapago = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarProveedor_Formapago($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$proveedor_formapago = $this->model->where('proveedor_id', $id)->get()->pluck('id')->toArray();
			$q_proveedor_formapago = count($proveedor_formapago);
		}

		// Graba formas de pago
		if (isset($data['nombres']))
		{
			$nombres = $data['nombres'];
			$formapago_ids = $data['formapago_ids'];
			$cbus = $data['cbus'];
			$tipocuentacaja_ids = $data['tipocuentacaja_ids'];
			$moneda_ids = $data['moneda_ids'];
			$numerocuentas = $data['numerocuentas'];
			$nroinscripciones = $data['nroinscripciones'];
			$banco_ids = $data['banco_ids'];
			$mediopago_ids = $data['mediopago_ids'];
			$emails = $data['emails'];
			if ($funcion == 'update')
			{
				$_id = $proveedor_formapago;

				// Borra las que sobran
				if ($q_proveedor_formapago > count($nombres))
				{
					for ($d = count($nombres); $d < $q_proveedor_formapago; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_proveedor_formapago && $i < count($nombres); $i++)
				{
					if ($i < count($nombres))
					{
						$proveedor_formapago = $this->model->findOrFail($_id[$i])->update([
									"proveedor_id" => $id,
									"nombre" => $nombres[$i],
									"formapago_id" => $formapago_ids[$i],
									"cbu" => $cbus[$i],
									"tipocuentacaja_id" => $tipocuentacaja_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"numerocuenta" => $numerocuentas[$i],
									"nroinscripcion" => $nroinscripciones[$i],
									"banco_id" => $banco_ids[$i],
									"mediopago_id" => $mediopago_ids[$i],
									"email" => $emails[$i]
									]);
					}
				}
				if ($q_proveedor_formapago > count($nombres))
					$i = $d; 
			}
			else
				$i = 0;

			// Agrega el resto de las formas de pago
			for ($i_formapago = $i; $i_formapago < count($nombres); $i_formapago++)
			{
				//* Valida si se cargo una formapago
				if ($nombres[$i_formapago] != '') 
				{
					$proveedor_formapago = $this->model->create([
										"proveedor_id" => $id,
										"nombre" => $nombres[$i_formapago],
										"formapago_id" => $formapago_ids[$i_formapago],
										"cbu" => $cbus[$i_formapago],
										"tipocuentacaja_id" => $tipocuentacaja_ids[$i_formapago],
										"moneda_id" => $moneda_ids[$i_formapago],
										"numerocuenta" => $numerocuentas[$i_formapago],
										"nroinscripcion" => $nroinscripciones[$i_formapago],
										"banco_id" => $banco_ids[$i_formapago],
										"mediopago_id" => $mediopago_ids[$i_formapago],
										"email" => $emails[$i_formapago]
									]);
				}
			}
		}
		else // Borra todas las formas de pago
		{
			$proveedor_formapago = $this->model->where('proveedor_id', $id)->delete();
		}
	}
}
