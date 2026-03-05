<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tipotransaccion_Compra_Centrocosto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Tipotransaccion_Compra_CentrocostoRepository implements Tipotransaccion_Compra_CentrocostoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipotransaccion_Compra_Centrocosto $tipotransaccion_compra_centrocosto)
    {
        $this->model = $tipotransaccion_compra_centrocosto;
    }

    public function create(array $data, $id)
    {
		return self::guardarTipotransaccion_Compra_Centrocosto($data, 'create', $id);
    }
	
    public function createUnRegistro(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarTipotransaccion_Compra_Centrocosto($data, 'update', $id);
    }

    public function delete($tipotransaccion_compra_id, $codigo)
    {
        return $this->model->where('tipotransaccion_compra_id', $tipotransaccion_compra_id)->delete();
    }

    public function find($id)
    {
        if (null == $tipotransaccion_compra_centrocosto = $this->model
											->with('centrocostos')->find($id)) 				
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion_compra_centrocosto;
    }

	public function leeTipotransaccion_Compra_Centrocosto($tipotransaccion_compra_id)
	{
		$tipotransaccion_compra_centrocosto = $this->model
				->with('centrocostos')
				->where('tipotransaccion_compra_id', $tipotransaccion_compra_id)->get();

		return $tipotransaccion_compra_centrocosto;
	}
	
    public function findOrFail($id)
    {
        if (null == $tipotransaccion_compra_centrocosto = $this->model
										->with('centrocostos')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarTipotransaccion_Compra_Centrocosto($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$tipotransaccion_compra_centrocosto = $this->model->where('tipotransaccion_compra_id', $id)->get()->pluck('id')->toArray();
			$q_tipotransaccion_compra_centrocosto = count($tipotransaccion_compra_centrocosto);
		}

		// Graba exclusiones
		if (isset($data['centrocosto_ids']))
		{
			$centrocosto_ids = $data['centrocosto_ids'];

			if ($funcion == 'update')
			{
				$_id = $tipotransaccion_compra_centrocosto;

				// Borra los que sobran
				if ($q_tipotransaccion_compra_centrocosto > count($centrocosto_ids))
				{
					for ($d = count($centrocosto_ids); $d < $q_tipotransaccion_compra_centrocosto; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_tipotransaccion_compra_centrocosto && $i < count($centrocosto_ids); $i++)
				{
					if ($i < count($centrocosto_ids))
					{
						$tipotransaccion_compra_centrocosto = $this->model->findOrFail($_id[$i])->update([
									"tipotransaccion_compra_id" => $id,
									"centrocosto_id" => $centrocosto_ids[$i]
									]);
					}
				}
				if ($q_tipotransaccion_compra_centrocosto > count($centrocosto_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_centrocosto = $i; $i_centrocosto < count($centrocosto_ids); $i_centrocosto++)
			{
				//* Valida si se cargo un centro de costo
				if ($centrocosto_ids[$i_centrocosto] != '') 
				{
					$tipotransaccion_compra_centrocosto = $this->model->create([
									"tipotransaccion_compra_id" => $id,
									"centrocosto_id" => $centrocosto_ids[$i_centrocosto]
									]);
				}
			}
		}
		else
		{
			$tipotransaccion_compra_centrocosto = $this->model->where('tipotransaccion_compra_id', $id)->delete();
		}
	}
}
