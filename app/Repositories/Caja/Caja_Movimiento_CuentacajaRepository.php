<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Repositories\Caja\Tipotransaccion_CajaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Caja_Movimiento_CuentacajaRepository implements Caja_Movimiento_CuentacajaRepositoryInterface
{
    protected $model;
	protected $tipotransaccion_cajaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Movimiento_Cuentacaja $caja_movimiento_cuentacaja,
								TipoTransaccion_CajaRepositoryInterface $tipotransaccion_cajarepository)
    {
        $this->model = $caja_movimiento_cuentacaja;
		$this->tipotransaccion_cajaRepository = $tipotransaccion_cajarepository;
    }

    public function create(array $data, $id)
    {
		return self::guardarCaja_Movimiento_Cuentacaja($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$caja_movimiento_cuentacaja = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCaja_Movimiento_Cuentacaja($data, 'update', $id);
    }

    public function delete($caja_movimiento_id, $codigo)
    {
        return $this->model->where('caja_movimiento_id', $caja_movimiento_id)->delete();
    }

    public function find($id)
    {
        if (null == $caja_movimiento_cuentacaja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_cuentacaja;
    }

    public function findOrFail($id)
    {
        if (null == $caja_movimiento_cuentacaja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja_movimiento_cuentacaja;
    }

	private function guardarCaja_Movimiento_Cuentacaja($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$caja_movimiento_cuentacaja = $this->model->where('caja_movimiento_id', $id)->get()->pluck('id')->toArray();
			$q_caja_movimiento_cuentacaja = count($caja_movimiento_cuentacaja);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$tipotransaccion_caja = $this->tipotransaccion_cajaRepository->find($data['tipotransaccion_caja_id']);

			$signo = 1;
			if ($tipotransaccion_caja)
			{
				if ($tipotransaccion_caja->signo == 'I')
					$signo = 1;
				else
					$signo = -1;
			}
			$cuentacaja_ids = $data['cuentacaja_ids'];
			$moneda_ids = $data['moneda_ids'];
			$montos = $data['montos'];
			$cotizaciones = $data['cotizaciones'];
			$observaciones = $data['observaciones'];
			$fecha = $data['fecha'];
			
			if ($funcion == 'update')
			{
				$_id = $caja_movimiento_cuentacaja;

				// Borra los que sobran
				if ($q_caja_movimiento_cuentacaja > count($cuentacaja_ids))
				{
					for ($d = count($cuentacaja_ids); $d < $q_caja_movimiento_cuentacaja; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_caja_movimiento_cuentacaja && $i < count($cuentacaja_ids); $i++)
				{
					if ($i < count($cuentacaja_ids))
					{
						$monto = 0;
						if ($montos[$i] != null && $montos[$i] != 0)
							$monto = $montos[$i] * $signo;

						$caja_movimiento_cuentacaja = $this->model->findOrFail($_id[$i])->update([
									"caja_movimiento_id" => $id,
									"fecha" => $fecha,
									"cuentacaja_id" => $cuentacaja_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $monto,
									"cotizacion" => $cotizaciones[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_caja_movimiento_cuentacaja > count($cuentacaja_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($cuentacaja_ids); $i_movimiento++)
			{
				if ($cuentacaja_ids[$i_movimiento] != '') 
				{
					if (!isset($observaciones[$i_movimiento]))
						$observaciones[$i_movimiento] = ' ';
					$monto = 0;
					if ($montos[$i_movimiento] != null && $montos[$i_movimiento] != 0)
						$monto = $montos[$i_movimiento] * $signo;
					$caja_movimiento_cuentacaja = $this->model->create([
						"caja_movimiento_id" => $id,
						"fecha" => $fecha,
						"cuentacaja_id" => $cuentacaja_ids[$i_movimiento],
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
			$caja_movimiento_cuentacaja = $this->model->where('caja_movimiento_id', $id)->delete();
		}

		return $caja_movimiento_cuentacaja;
	}
}
