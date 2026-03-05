<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Voucher_Reserva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Voucher_ReservaRepository implements Voucher_ReservaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Voucher_Reserva $voucher_reserva)
    {
        $this->model = $voucher_reserva;
    }

    public function create(array $data, $id)
    {
		return self::guardarVoucher_Reserva($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
		return self::guardarVoucher_Reserva($data, 'update', $id);
    }

    public function delete($voucher_id, $codigo)
    {
        $voucher_reserva = $this->model->where('voucher_id', $voucher_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        if (null == $voucher_reserva = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	public function leeVoucherReserva($voucher_id)
	{
		$voucher_reserva = $this->model->where('voucher_id', $voucher_id)->get();

		return $voucher_reserva;
	}
	
    public function findOrFail($id)
    {
        if (null == $voucher_reserva = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

	private function guardarVoucher_Reserva($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$voucher_reserva = $this->model->where('voucher_id', $id)->get()->pluck('id')->toArray();
			$q_voucher_reserva = count($voucher_reserva);
		}

		// Graba reservas
		if (isset($data['reserva_ids']))
		{
			$reserva_ids = $data['reserva_ids'];
			$pasajero_ids = $data['pasajero_ids'];
			$nombrepasajeros = $data['nombrepasajeros'];
			$fechaarribos = $data['fechaarribos'];
			$fechapartidas = $data['fechapartidas'];
			$paxs = $data['paxs'];
			$frees = $data['frees'];
			$limitefrees = $data['limitefrees'];
			$incluidos = $data['incluidos'];
			$opcionales = $data['opcionales'];

			if ($funcion == 'update')
			{
				$_id = $voucher_reserva;

				// Borra los que sobran
				if ($q_voucher_reserva > count($reserva_ids))
				{
					for ($d = count($reserva_ids); $d < $q_voucher_reserva; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_voucher_reserva && $i < count($reserva_ids); $i++)
				{
					if ($i < count($reserva_ids))
					{
						$voucher_reserva = $this->model->findOrFail($_id[$i])->update([
									"voucher_id" => $id,
									"reserva_id" => $reserva_ids[$i],
									"pasajero_id" => $pasajero_ids[$i],
									"nombrepasajero" => $nombrepasajeros[$i],
									"fechaarribo" => $fechaarribos[$i],
									"fechapartida" => $fechapartidas[$i],
									"pax" => $paxs[$i],
									"free" => $frees[$i],
									"limitefree" => $limitefrees[$i],
									"incluido" => $incluidos[$i],
									"opcional" => $opcionales[$i],
									]);
					}
				}
				if ($q_voucher_reserva > count($reserva_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_reserva = $i; $i_reserva < count($reserva_ids); $i_reserva++)
			{
				if ($reserva_ids[$i_reserva] != '') 
				{
					$voucher_reserva = $this->model->create([
									"voucher_id" => $id,
									"reserva_id" => $reserva_ids[$i_reserva],
									"pasajero_id" => $pasajero_ids[$i_reserva],
									"nombrepasajero" => $nombrepasajeros[$i_reserva],
									"fechaarribo" => $fechaarribos[$i_reserva],
									"fechapartida" => $fechapartidas[$i_reserva],
									"pax" => $paxs[$i_reserva],
									"free" => $frees[$i_reserva],
									"limitefree" => $limitefrees[$i_reserva],
									"incluido" => $incluidos[$i_reserva],
									"opcional" => $opcionales[$i_reserva],
									]);
				}
			}
		}
		else
		{
			$voucher_reserva = $this->model->where('voucher_id', $id)->delete();
		}
	}
}
