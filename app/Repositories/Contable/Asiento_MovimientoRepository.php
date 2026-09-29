<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Asiento_Movimiento;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
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
        $eliminados = 0;
        foreach ($this->model->where('asiento_id', $asiento_id)->get() as $movimiento) {
            $movimiento->delete();
            $eliminados++;
        }

        return $eliminados;
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

	/**
	 * Conserva factura y OC originales del renglón al grabar el formulario.
	 *
	 * @param  array<string, mixed>  $data
	 * @return array<string, mixed>
	 */
	private function documentoLineaDesdeRequest(array $data, int $i): array
	{
		if (! Schema::hasColumn('asiento_movimiento', 'anita_nro')) {
			return [];
		}
		if (! array_key_exists('mov_anita_nro', $data) && ! array_key_exists('mov_nro_ordencompra', $data)) {
			return [];
		}

		$nro = (int) ($data['mov_anita_nro'][$i] ?? 0);
		$oc = (int) ($data['mov_nro_ordencompra'][$i] ?? 0);
		$tipo = strtoupper(trim((string) ($data['mov_anita_tipo'][$i] ?? '')));
		$letra = trim((string) ($data['mov_anita_letra'][$i] ?? ''));

		return [
			'anita_tipo' => $nro > 0 && $tipo !== '' ? substr($tipo, 0, 10) : null,
			'anita_letra' => $nro > 0 && $letra !== '' ? substr($letra, 0, 3) : null,
			'anita_sucursal' => $nro > 0 ? (int) ($data['mov_anita_sucursal'][$i] ?? 0) : null,
			'anita_nro' => $nro > 0 ? $nro : null,
			'nro_ordencompra' => $oc > 0 ? $oc : null,
		];
	}

	private function normalizarCentrocostoId($centrocostoId)
	{
		if ($centrocostoId === null || $centrocostoId === '' || $centrocostoId === 0 || $centrocostoId === '0') {
			return null;
		}

		return $centrocostoId;
	}

	private function guardarAsiento_Movimiento($data, $funcion, $id = null)
	{
		// En create, si todas las líneas se omiten (monto ~0 / sin cuenta), no se asignaba
		// y el return disparaba "Undefined variable $asiento_movimiento".
		$asiento_movimiento = null;

		if ($funcion == 'update')
		{
			// Trae todos los id
        	$asiento_movimiento = $this->model->where('asiento_id', $id)->get()->pluck('id')->toArray();
			$q_asiento_movimiento = count($asiento_movimiento);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$cuentacontable_ids = $data['cuentacontable_ids'] ?? [];
			$centrocosto_ids = $data['centrocosto_ids'] ?? [];
			$centrocosto_ids_previo = $data['centrocosto_id_previo'] ?? [];
			$moneda_ids = $data['moneda_ids'] ?? [];
			$debes = $data['debes'] ?? [];
			$haberes = $data['haberes'] ?? [];
			$cotizaciones = $data['cotizaciones'] ?? [];
			$observaciones = $data['observaciones'] ?? [];

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
						$debeLin = NumeroDecimalLocalSupport::aFloat($debes[$i] ?? null);
						$haberLin = NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? null);
						if ($debeLin != 0)
							$monto = $debeLin;

						if ($haberLin != 0)
							$monto = -$haberLin;

						$ccId = $centrocosto_ids[$i] ?? $centrocosto_ids_previo[$i] ?? 0;

						$asiento_movimiento = $this->model->findOrFail($_id[$i])->update(array_merge([
									"asiento_id" => $id,
									"cuentacontable_id" => $cuentacontable_ids[$i],
									"centrocosto_id" => $this->normalizarCentrocostoId($ccId),
									"moneda_id" => $moneda_ids[$i] ?? null,
									"monto" => $monto,
									"cotizacion" => NumeroDecimalLocalSupport::aFloat($cotizaciones[$i] ?? 0),
									"observacion" => $observaciones[$i] ?? ''
									], $this->documentoLineaDesdeRequest($data, $i)));
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
					$debeLin = NumeroDecimalLocalSupport::aFloat($debes[$i_movimiento] ?? null);
					$haberLin = NumeroDecimalLocalSupport::aFloat($haberes[$i_movimiento] ?? null);
					if ($debeLin != 0)
						$monto = $debeLin;

					if ($haberLin != 0)
						$monto = -$haberLin;

					if (abs($monto) <= 0.0001) {
						continue;
					}

					$ccId = $centrocosto_ids[$i_movimiento] ?? $centrocosto_ids_previo[$i_movimiento] ?? 0;

					$asiento_movimiento = $this->model->create(array_merge([
									"asiento_id" => $id,
									"cuentacontable_id" => $cuentacontable_ids[$i_movimiento],
									"centrocosto_id" => $this->normalizarCentrocostoId($ccId),
									"moneda_id" => $moneda_ids[$i_movimiento] ?? null,
									"monto" => $monto,
									"cotizacion" => NumeroDecimalLocalSupport::aFloat($cotizaciones[$i_movimiento] ?? 0),
									"observacion" => $observaciones[$i_movimiento] ?? ''
									], $this->documentoLineaDesdeRequest($data, $i_movimiento)));
				}
			}
		}
		else
		{
			foreach ($this->model->where('asiento_id', $id)->get() as $movimiento) {
				$movimiento->delete();
			}
		}

		return $asiento_movimiento;
	}
}
