<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cobranza_Retencion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Cobranza_RetencionRepository implements Cobranza_RetencionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cobranza_Retencion $cobranza_retencion)
    {
        $this->model = $cobranza_retencion;
    }

    public function create(array $data, $id)
    {
		return self::guardarCobranza_Retencion($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$cobranza_retencion = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarCobranza_Retencion($data, 'update', $id);
    }

    public function delete($cobranza_id, $codigo)
    {
        return $this->model->where('cobranza_id', $cobranza_id)->delete();
    }

    public function find($id)
    {
        if (null == $cobranza_retencion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_retencion;
    }

    public function findOrFail($id)
    {
        if (null == $cobranza_retencion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cobranza_retencion;
    }

	private function guardarCobranza_Retencion($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cobranza_retencion = $this->model->where('cobranza_id', $id)->get()->pluck('id')->toArray();
			$q_cobranza_retencion = count($cobranza_retencion);
		}

		// Graba retenciones
		if (isset($data['retencion_cobranza_ids']))
		{
			$retencion_cobranza_ids = $data['retencion_cobranza_ids'];
			$comprobantes = $data['comprobante_retenciones'];
			$moneda_ids = $data['moneda_retencion_ids'];
			$montos = $data['monto_retenciones'];
			$tasas = $data['tasa_retenciones'];
			$cotizaciones = $data['cotizacion_retenciones'];
			
			if ($funcion == 'update')
			{
				$_id = $cobranza_retencion;

				// Borra los que sobran
				if ($q_cobranza_retencion > count($retencion_cobranza_ids))
				{
					for ($d = count($retencion_cobranza_ids); $d < $q_cobranza_retencion; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cobranza_retencion && $i < count($retencion_cobranza_ids); $i++)
				{
					if ($i < count($retencion_cobranza_ids))
					{
						$cobranza_retencion = $this->model->findOrFail($_id[$i])->update([
									"cobranza_id" => $id,
									"retencion_cobranza_id" => $retencion_cobranza_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $montos[$i],
									"tasa" => $tasas[$i],
									"cotizacion" => $cotizaciones[$i],
									"comprobante" => $comprobantes[$i]
									]);
					}
				}
				if ($q_cobranza_retencion > count($retencion_cobranza_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($retencion_cobranza_ids); $i_movimiento++)
			{
				if ($retencion_cobranza_ids[$i_movimiento] != '') 
				{
					$cobranza_retencion = $this->model->create([
						"cobranza_id" => $id,
						"retencion_cobranza_id" => $retencion_cobranza_ids[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"tasa" => $tasas[$i_movimiento],
						"cotizacion" => $cotizaciones[$i_movimiento],
						"comprobante" => $comprobantes[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$cobranza_retencion = $this->model->where('cobranza_id', $id)->delete();
		}

		return $cobranza_retencion;
	}
}
