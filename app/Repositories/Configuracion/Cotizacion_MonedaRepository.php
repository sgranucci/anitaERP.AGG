<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Cotizacion_Moneda;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cotizacion_MonedaRepository implements Cotizacion_MonedaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cotizacion_Moneda $cotizacion_moneda)
    {
        $this->model = $cotizacion_moneda;
    }

    public function create(array $data, $id)
    {
		return self::guardarCotizacion_Moneda($data, 'create', $id);
    }

	public function createDirecto(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return self::guardarCotizacion_Moneda($data, 'update', $id);
    }

    public function delete($fecha)
    {
        return $this->model->where('fecha', $fecha)->delete();
    }

    public function find($id)
    {
        if (null == $cotizacion_moneda = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cotizacion_moneda;
    }

    public function findOrFail($id)
    {
        if (null == $cotizacion_moneda = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cotizacion_moneda;
    }

	private function guardarCotizacion_Moneda($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cotizacion_moneda = $this->model->where('cotizacion_id', $id)->get()->pluck('id')->toArray();
			$q_cotizacion_moneda = count($cotizacion_moneda);
		}

		// Graba cotizaciones
		if (isset($data['moneda_ids']))
		{
			$moneda_ids = $data['moneda_ids'];
			$cotizacionVentas = $data['cotizacionventas'];
			$cotizacionCompras = $data['cotizacioncompras'];

			if ($funcion == 'update')
			{
				$_id = $cotizacion_moneda;

				// Borra los que sobran
				if ($q_cotizacion_moneda > count($moneda_ids))
				{
					for ($d = count($moneda_ids); $d < $q_cotizacion_moneda; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cotizacion_moneda && $i < count($moneda_ids); $i++)
				{
					if ($i < count($moneda_ids))
					{
						$cotizacion_moneda = $this->model->findOrFail($_id[$i])->update([
									"cotizacion_id" => $id,
									"moneda_id" => $moneda_ids[$i],
									"cotizacionventa" => $cotizacionVentas[$i],
									"cotizacioncompra" => $cotizacionCompras[$i]
									]);
					}
				}
				if ($q_cotizacion_moneda > count($moneda_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($moneda_ids); $i_movimiento++)
			{
				//* Valida si se cargo una cotizacion
				if ($moneda_ids[$i_movimiento] != '') 
				{
					$cotizacion_moneda = $this->model->create([
									"cotizacion_id" => $id,
									"moneda_id" => $moneda_ids[$i_movimiento],
									"cotizacionventa" => $cotizacionVentas[$i_movimiento],
									"cotizacioncompra" => $cotizacionCompras[$i_movimiento]
									]);
				}
			}
		}
		else
		{
			$cotizacion_moneda = $this->model->where('cotizacion_id', $id)->delete();
		}
	}
}
