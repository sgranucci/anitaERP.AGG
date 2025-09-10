<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Voucher_Guia;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;
use DB;

class Voucher_GuiaRepository implements Voucher_GuiaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Voucher_Guia $voucher_guia)
    {
        $this->model = $voucher_guia;
    }

    public function create(array $data, $id)
    {
		return self::guardarVoucher_Guia($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
		return self::guardarVoucher_Guia($data, 'update', $id);
    }

    public function delete($voucher_id, $codigo)
    {
        $voucher_guia = $this->model->where('voucher_id', $voucher_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        if (null == $voucher_guia = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeVoucherGuia($voucher_id)
	{
		$voucher_guia = $this->model->where('voucher_id', $voucher_id)->get();

		return $voucher_guia;
	}
	
    public function findOrFail($id)
    {
        if (null == $voucher_guia = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarVoucher_Guia($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$voucher_guia = $this->model->where('voucher_id', $id)->get()->pluck('id')->toArray();
			$q_voucher_guia = count($voucher_guia);
		}

		// Graba exclusiones
		if (isset($data['guia_ids']))
		{
			$guia_ids = $data['guia_ids'];
			$tipocomisiones = $data['tipocomisiones'];
			$porcentajecomisiones = $data['porcentajecomisiones'];
			$montocomisiones = $data['montocomisiones'];
			$ordenservicio_ids = $data['ordenservicio_ids'];

			if ($funcion == 'update')
			{
				$_id = $voucher_guia;

				// Borra los que sobran
				if ($q_voucher_guia > count($guia_ids))
				{
					for ($d = count($guia_ids); $d < $q_voucher_guia; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_voucher_guia && $i < count($guia_ids); $i++)
				{
					if ($i < count($guia_ids))
					{
						$voucher_guia = $this->model->findOrFail($_id[$i])->update([
									"voucher_id" => $id,
									"guia_id" => $guia_ids[$i],
									"tipocomision" => $tipocomisiones[$i],
									"porcentajecomision" => $porcentajecomisiones[$i],
									"montocomision" => $montocomisiones[$i],
									"ordenservicio_id" => $ordenservicio_ids[$i]
									]);
					}
				}
				if ($q_voucher_guia > count($guia_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_guia = $i; $i_guia < count($guia_ids); $i_guia++)
			{
				//* Valida si se cargo una exclusion
				if ($guia_ids[$i_guia] != '') 
				{
					$voucher_guia = $this->model->create([
									"voucher_id" => $id,
									"guia_id" => $guia_ids[$i_guia],
									"tipocomision" => $tipocomisiones[$i_guia],
									"porcentajecomision" => $porcentajecomisiones[$i_guia],
									"montocomision" => $montocomisiones[$i_guia],
									"ordenservicio_id" => $ordenservicio_ids[$i_guia]
									]);
				}
			}
		}
		else
		{
			$voucher_guia = $this->model->where('voucher_id', $id)->delete();
		}
	}

	function leeComisionPorGuiaOrdenservicio($guia_id, $ordenservicio_id)
	{
		$voucher = $this->model->select('voucher_guia.voucher_id as id',
										'voucher.fecha as fecha',
										DB::raw(config('receptivo.comisiones.CUENTA_CAJA_ID').' as cuentacaja_id'),
										DB::raw('(SELECT codigo FROM cuentacaja WHERE id ='.config('receptivo.comisiones.CUENTA_CAJA_ID').') as codigocuentacaja'),
										DB::raw('(SELECT nombre FROM cuentacaja WHERE id ='.config('receptivo.comisiones.CUENTA_CAJA_ID').') as nombrecuentacaja'),
										DB::raw('(SELECT abreviatura FROM moneda WHERE id ='.config('receptivo.comisiones.MONEDA_ID').') as abreviaturamoneda'),
										DB::raw(config('receptivo.comisiones.MONEDA_ID').' as moneda_id'),
										'voucher_guia.montocomision as monto',
										DB::raw(config('receptivo.comisiones.COTIZACION').' as cotizacion'),
										'voucher_guia.ordenservicio_id as ordenservicio_id')
										->leftJoin('voucher', 'voucher.id', 'voucher_guia.voucher_id')
										->where([
												['voucher_guia.ordenservicio_id', $ordenservicio_id],
												['voucher_guia.guia_id', $guia_id]
												])
										->get();

		return $voucher;
	}

	// Lee ordenes de servicio cargadas en vouches

    public function leeOrdenServicioVoucher()
	{
		$voucher = $this->model->select('voucher_guia.ordenservicio_id as ordenservicio_id')
								->whereNotExists(function ($query) {
									$query->select(DB::raw(1))
											->from('rendicionreceptivo')
											->where('deleted_at', null)
											->whereColumn('voucher_guia.ordenservicio_id', 'rendicionreceptivo.ordenservicio_id');
								})
								->get();

		return $voucher;
	}
}
