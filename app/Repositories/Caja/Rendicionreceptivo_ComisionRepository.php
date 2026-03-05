<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo_Comision;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Rendicionreceptivo_ComisionRepository implements Rendicionreceptivo_ComisionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo_Comision $rendicionreceptivo_comision)
    {
        $this->model = $rendicionreceptivo_comision;
    }

    public function create(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Comision($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$rendicionreceptivo_comision = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Comision($data, 'update', $id);
    }

    public function delete($rendicionreceptivo_id, $codigo)
    {
        return $this->model->where('rendicionreceptivo_id', $rendicionreceptivo_id)->delete();
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo_comision = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_comision;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo_comision = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_comision;
    }

	private function guardarRendicionreceptivo_Comision($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$rendicionreceptivo_comision = $this->model->where('rendicionreceptivo_id', $id)->get()->pluck('id')->toArray();
			$q_rendicionreceptivo_comision = count($rendicionreceptivo_comision);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$voucher_ids = $data['vouchercomision_ids'];
			$cuentacaja_ids = $data['cuentacajacomision_ids'];
			$moneda_ids = $data['monedacomision_ids'];
			$montos = $data['montocomisiones'];
			$cotizaciones = $data['cotizacioncomisiones'];
			if ($funcion == 'update')
			{
				$_id = $rendicionreceptivo_comision;

				// Borra los que sobran
				if ($q_rendicionreceptivo_comision > count($cuentacaja_ids))
				{
					for ($d = count($moneda_ids); $d < $q_rendicionreceptivo_comision; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_rendicionreceptivo_comision && $i < count($cuentacaja_ids); $i++)
				{
					if ($i < count($moneda_ids))
					{
						$rendicionreceptivo_comision = $this->model->findOrFail($_id[$i])->update([
									"rendicionreceptivo_id" => $id,
									"voucher_id" => $voucher_ids[$i],
									"cuentacaja_id" => $cuentacaja_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $montos[$i],
									"cotizacion" => $cotizaciones[$i]
									]);
					}
				}
				if ($q_rendicionreceptivo_comision > count($cuentacaja_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($cuentacaja_ids); $i_movimiento++)
			{
				if ($cuentacaja_ids[$i_movimiento] != '') 
				{
					$rendicionreceptivo_comision = $this->model->create([
						"rendicionreceptivo_id" => $id,
						"voucher_id" => $voucher_ids[$i_movimiento],
						"cuentacaja_id" => $cuentacaja_ids[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"cotizacion" => $cotizaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$rendicionreceptivo_comision = $this->model->where('rendicionreceptivo_id', $id)->delete();
		}

		return $rendicionreceptivo_comision;
	}
}
