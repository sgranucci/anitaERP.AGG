<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Ordenventa_Cuota;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ordenventa_CuotaRepository implements Ordenventa_CuotaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenventa_Cuota $ordenventa_cuota)
    {
        $this->model = $ordenventa_cuota;
    }

    public function create(array $data, $id)
    {
		return self::guardarOrdenventa_Cuota($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$ordenventa_cuota = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarOrdenventa_Cuota($data, 'update', $id);
    }

    public function delete($ordenventa_id, $codigo)
    {
        return $this->model->where('ordenventa_id', $ordenventa_id)->delete();
    }

    public function find($id)
    {
        if (null == $ordenventa_cuota = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_cuota;
    }

    public function findOrFail($id)
    {
        if (null == $ordenventa_cuota = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenventa_cuota;
    }

	private function guardarOrdenventa_Cuota($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ordenventa_cuota = $this->model->where('ordenventa_id', $id)->get()->pluck('id')->toArray();
			$q_ordenventa_cuota = count($ordenventa_cuota);
		}

		// Graba estados
		if (isset($data))
		{
			if (isset($data['montofacturas']))
			{
				$fechafacturas = $data['fechafacturas'];
				$montofacturas = $data['montofacturas'];
			}
			else
			{
				$montofacturas = [];
				$fechafacturas = [];
			}

			if ($funcion == 'update')
			{
				$_id = $ordenventa_cuota;

				// Borra los que sobran
				if ($q_ordenventa_cuota > count($fechafacturas))
				{
					for ($d = count($fechafacturas); $d < $q_ordenventa_cuota; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ordenventa_cuota && $i < count($fechafacturas); $i++)
				{
					if ($i < count($fechafacturas))
					{
						$ordenventa_cuota = $this->model->findOrFail($_id[$i])->update([
									"ordenventa_id" => $id,
									"fechafactura" => $fechafacturas[$i],
									"montofactura" => $montofacturas[$i]
									]);
					}
				}
				if ($q_ordenventa_cuota > count($montofacturas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($montofacturas); $i_movimiento++)
			{
				if ($montofacturas[$i_movimiento] != '') 
				{
					$ordenventa_cuota = $this->model->create([
						"ordenventa_id" => $id,
						"fechafactura" => $fechafacturas[$i_movimiento],
						"montofactura" => $montofacturas[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$ordenventa_cuota = $this->model->where('ordenventa_id', $id)->delete();
		}

		return $ordenventa_cuota;
	}
}
