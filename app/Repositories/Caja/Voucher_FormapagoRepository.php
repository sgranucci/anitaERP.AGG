<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Voucher_Formapago;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Voucher_FormapagoRepository implements Voucher_FormapagoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Voucher_Formapago $voucher_formapago)
    {
        $this->model = $voucher_formapago;
    }

    public function create(array $data, $id)
    {
		return self::guardarVoucher_Formapago($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
		return self::guardarVoucher_Formapago($data, 'update', $id);
    }

    public function delete($voucher_id, $codigo)
    {
        $voucher_formapago = $this->model->where('voucher_id', $voucher_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        if (null == $voucher_formapago = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeVoucherFormapago($voucher_id)
	{
		$voucher_formapago = $this->model->where('voucher_id', $voucher_id)->get();

		return $voucher_formapago;
	}
	
    public function findOrFail($id)
    {
        if (null == $voucher_formapago = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarVoucher_Formapago($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$voucher_formapago = $this->model->where('voucher_id', $id)->get()->pluck('id')->toArray();
			$q_voucher_formapago = count($voucher_formapago);
		}

		// Graba forma de pago
		if (isset($data['cuentacaja_ids']))
		{
			$cuentacaja_ids = $data['cuentacaja_ids'];
			$moneda_ids = $data['moneda_ids'];
			$montos = $data['montos'];
			$cotizaciones = $data['cotizaciones'];

			if ($funcion == 'update')
			{
				$_id = $voucher_formapago;

				// Borra los que sobran
				if ($q_voucher_formapago > count($cuentacaja_ids))
				{
					for ($d = count($cuentacaja_ids); $d < $q_voucher_formapago; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_voucher_formapago && $i < count($cuentacaja_ids); $i++)
				{
					if ($i < count($cuentacaja_ids))
					{
						$voucher_formapago = $this->model->findOrFail($_id[$i])->update([
									"voucher_id" => $id,
									"cuentacaja_id" => $cuentacaja_ids[$i],
									"moneda_id" => $moneda_ids[$i],
									"monto" => $montos[$i],
									"cotizacion" => $cotizaciones[$i],
									]);
					}
				}
				if ($q_voucher_formapago > count($cuentacaja_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_formapago = $i; $i_formapago < count($cuentacaja_ids); $i_formapago++)
			{
				//* Valida si se cargo una exclusion
				if ($cuentacaja_ids[$i_formapago] != '') 
				{
					$voucher_formapago = $this->model->create([
									"voucher_id" => $id,
									"cuentacaja_id" => $cuentacaja_ids[$i_formapago],
									"moneda_id" => $moneda_ids[$i_formapago],
									"monto" => $montos[$i_formapago],
									"cotizacion" => $cotizaciones[$i_formapago],
									]);
				}
			}
		}
		else
		{
			$voucher_formapago = $this->model->where('voucher_id', $id)->delete();
		}
	}
}
