<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento_Movimiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Asiento_MovimientoRepository implements Asiento_MovimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Asiento_Movimiento $asiento_movimiento)
    {
        $this->model = $asiento_movimiento;
    }

    public function create(array $data, $id)
    {
		return self::guardarAsiento_Movimiento($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$asiento_movimiento = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarAsiento_Movimiento($data, 'update', $id);
    }

    public function delete($asiento_id, $codigo)
    {
        return $this->model->where('asiento_id', $asiento_id)->delete();
    }

    public function find($id)
    {
        if (null == $asiento_movimiento = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $asiento_movimiento;
    }

	public function leeAsientoMovimiento($asiento_id)
	{
		$asiento_movimiento = $this->model->where('asiento_id', $asiento_id)->get();

		return $asiento_movimiento;
	}
	
    public function findOrFail($id)
    {
        if (null == $asiento_movimiento = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $asiento_movimiento;
    }

	private function guardarAsiento_Movimiento($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$asiento_movimiento = $this->model->where('asiento_id', $id)->get()->pluck('id')->toArray();
			$q_asiento_movimiento = count($asiento_movimiento);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$cuentacontable_ids = $data['cuentacontable_ids'];
			$centrocosto_ids = $data['centrocosto_ids'];
			$moneda_ids = $data['moneda_ids'];
			$debes = $data['debes'];
			$haberes = $data['haberes'];
			$cotizaciones = $data['cotizaciones'];
			$observaciones = $data['observaciones'];

			if ($funcion == 'update')
			{
				$_id = $asiento_movimiento;

				// Borra los que sobran
				if ($q_asiento_movimiento > count($cuentacontable_ids))
				{
					for ($d = count($cuentacontable_ids); $d < $q_asiento_movimiento; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_asiento_movimiento && $i < count($cuentacontable_ids); $i++)
				{
					if ($i < count($cuentacontable_ids))
					{
						$monto = 0;
						if ($debes[$i] != null && $debes[$i] != 0)
							$monto = $debes[$i];

						if ($haberes[$i] != null && $haberes[$i] != 0)
							$monto = -$haberes[$i];

						$asiento_movimiento = $this->model->findOrFail($_id[$i])->update([
									"asiento_id" => $id,
									"cuentacontable_id" => $cuentacontable_ids[$i],
									"centrocosto_id" => $centrocosto_ids[$i] === '0' ? null : $centrocosto_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $monto,
									"cotizacion" => $cotizaciones[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_asiento_movimiento > count($cuentacontable_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($cuentacontable_ids); $i_movimiento++)
			{
				//* Valida si se cargo una exclusion
				if ($cuentacontable_ids[$i_movimiento] != '') 
				{
					$monto = 0;
					if ($debes[$i_movimiento] != null && $debes[$i_movimiento] != 0)
						$monto = $debes[$i_movimiento];

					if ($haberes[$i_movimiento] != null && $haberes[$i_movimiento] != 0)
						$monto = -$haberes[$i_movimiento];

					$asiento_movimiento = $this->model->create([
									"asiento_id" => $id,
									"cuentacontable_id" => $cuentacontable_ids[$i_movimiento],
									"centrocosto_id" => $centrocosto_ids[$i_movimiento] === '0' ? null : $centrocosto_ids[$i_movimiento],
									"moneda_id" => $moneda_ids[$i_movimiento],
									"monto" => $monto,
									"cotizacion" => $cotizaciones[$i_movimiento],
									"observacion" => $observaciones[$i_movimiento]
									]);
				}
			}
		}
		else
		{
			$asiento_movimiento = $this->model->where('asiento_id', $id)->delete();
		}

		return $asiento_movimiento;
	}
}
