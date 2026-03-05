<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tipotransaccion_Compra_Concepto_Ivacompra;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Tipotransaccion_Compra_Concepto_IvacompraRepository implements Tipotransaccion_Compra_Concepto_IvacompraRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipotransaccion_Compra_Concepto_Ivacompra $tipotransaccion_compra_concepto_ivacompra)
    {
        $this->model = $tipotransaccion_compra_concepto_ivacompra;
    }

    public function create(array $data, $id)
    {
		return self::guardarTipotransaccion_Compra_Concepto_Ivacompra($data, 'create', $id);
    }

    public function createUnRegistro(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarTipotransaccion_Compra_Concepto_Ivacompra($data, 'update', $id);
    }

    public function delete($Tipotransaccion_Compra_id, $codigo)
    {
        return $this->model->where('tipotransaccion_compra_id', $Tipotransaccion_Compra_id)->delete();
    }

    public function find($id)
    {
        if (null == $Tipotransaccion_Compra_Concepto_Ivacompra = $this->model
											->with('concepto_ivacompras')->find($id)) 				
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeTipotransaccion_Compra_Concepto_Ivacompra($tipotransaccion_compra_id)
	{
		$tipotransaccion_compra_concepto_ivacompra = $this->model
				->with('concepto_ivacompras')
				->where('tipotransaccion_compra_id', $tipotransaccion_compra_id)->get();

		return $tipotransaccion_compra_concepto_ivacompra;
	}
	
    public function findOrFail($id)
    {
        if (null == $tipotransaccion_compra_concepto_ivacompra = $this->model
										->with('concepto_ivacompras')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion_compra_concepto_ivacompra;
    }

	private function guardarTipotransaccion_Compra_Concepto_Ivacompra($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$tipotransaccion_compra_concepto_ivacompra = $this->model->where('tipotransaccion_compra_id', $id)
														->get()->pluck('id')->toArray();
			$q_tipotransaccion_compra_concepto_ivacompra = count($tipotransaccion_compra_concepto_ivacompra);
		}

		// Graba exclusiones
		if (isset($data['concepto_ivacompra_ids']))
		{
			$Concepto_Ivacompra_ids = $data['concepto_ivacompra_ids'];

			if ($funcion == 'update')
			{
				$_id = $tipotransaccion_compra_concepto_ivacompra;

				// Borra los que sobran
				if ($q_tipotransaccion_compra_concepto_ivacompra > count($Concepto_Ivacompra_ids))
				{
					for ($d = count($Concepto_Ivacompra_ids); $d < $q_tipotransaccion_compra_concepto_ivacompra; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_tipotransaccion_compra_concepto_ivacompra && $i < count($Concepto_Ivacompra_ids); $i++)
				{
					if ($i < count($Concepto_Ivacompra_ids))
					{
						$tipotransaccion_compra_concepto_ivacompra = $this->model->findOrFail($_id[$i])->update([
									"tipotransaccion_compra_id" => $id,
									"concepto_ivacompra_id" => $Concepto_Ivacompra_ids[$i]
									]);
					}
				}
				if ($q_tipotransaccion_compra_concepto_ivacompra > count($Concepto_Ivacompra_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_Concepto_Ivacompra = $i; $i_Concepto_Ivacompra< count($Concepto_Ivacompra_ids); $i_Concepto_Ivacompra++)
			{
				//* Valida si se cargo un centro de costo
				if ($Concepto_Ivacompra_ids[$i_Concepto_Ivacompra] != '') 
				{
					$tipotransaccion_compra_concepto_ivacompra = $this->model->create([
									"tipotransaccion_compra_id" => $id,
									"concepto_ivacompra_id" => $Concepto_Ivacompra_ids[$i_Concepto_Ivacompra]
									]);
				}
			}
		}
		else
		{
			$tipotransaccion_compra_concepto_ivacompra = $this->model->where('tipotransaccion_compra_id', $id)->delete();
		}
	}
}
