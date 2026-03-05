<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo_Formapago;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Rendicionreceptivo_FormapagoRepository implements Rendicionreceptivo_FormapagoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo_Formapago $rendicionreceptivo_formapago)
    {
        $this->model = $rendicionreceptivo_formapago;
    }

    public function create(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Formapago($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$rendicionreceptivo_formapago = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Formapago($data, 'update', $id);
    }

    public function delete($rendicionreceptivo_id, $codigo)
    {
        return $this->model->where('rendicionreceptivo_id', $rendicionreceptivo_id)->delete();
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo_formapago = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_formapago;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo_formapago = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_formapago;
    }

	private function guardarRendicionreceptivo_Formapago($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$rendicionreceptivo_formapago = $this->model->where('rendicionreceptivo_id', $id)->get()->pluck('id')->toArray();
			$q_rendicionreceptivo_formapago = count($rendicionreceptivo_formapago);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$signo = -1;
			$cuentacaja_ids = $data['cuentacajavoucher_ids'];
			$moneda_ids = $data['monedavoucher_ids'];
			$montos = $data['montovoucheres'];
			$cotizaciones = $data['cotizacionvoucheres'];
			if ($funcion == 'update')
			{
				$_id = $rendicionreceptivo_formapago;

				// Borra los que sobran
				if ($q_rendicionreceptivo_formapago > count($cuentacaja_ids))
				{
					for ($d = count($cuentacaja_ids); $d < $q_rendicionreceptivo_formapago; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_rendicionreceptivo_formapago && $i < count($cuentacaja_ids); $i++)
				{
					if ($i < count($cuentacaja_ids))
					{
						$monto = 0;
						if ($montos[$i] != null && $montos[$i] != 0)
							$monto = $montos[$i] * $signo;

						$rendicionreceptivo_formapago = $this->model->findOrFail($_id[$i])->update([
									"rendicionreceptivo_id" => $id,
									"cuentacaja_id" => $cuentacaja_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $monto,
									"cotizacion" => $cotizaciones[$i]
									]);
					}
				}
				if ($q_rendicionreceptivo_formapago > count($cuentacaja_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($cuentacaja_ids); $i_movimiento++)
			{
				if ($cuentacaja_ids[$i_movimiento] != '') 
				{
					$monto = 0;
					if ($montos[$i_movimiento] != null && $montos[$i_movimiento] != 0)
						$monto = $montos[$i_movimiento] * $signo;

					$rendicionreceptivo_formapago = $this->model->create([
						"rendicionreceptivo_id" => $id,
						"cuentacaja_id" => $cuentacaja_ids[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"monto" => $monto,
						"cotizacion" => $cotizaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$rendicionreceptivo_formapago = $this->model->where('rendicionreceptivo_id', $id)->delete();
		}

		return $rendicionreceptivo_formapago;
	}
}
