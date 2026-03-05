<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Rendicionreceptivo_Voucher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Rendicionreceptivo_VoucherRepository implements Rendicionreceptivo_VoucherRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Rendicionreceptivo_Voucher $rendicionreceptivo_voucher)
    {
        $this->model = $rendicionreceptivo_voucher;
    }

    public function create(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Voucher($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$rendicionreceptivo_voucher = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarRendicionreceptivo_Voucher($data, 'update', $id);
    }

    public function delete($rendicionreceptivo_id, $codigo)
    {
        return $this->model->where('rendicionreceptivo_id', $rendicionreceptivo_id)->delete();
    }

    public function find($id)
    {
        if (null == $rendicionreceptivo_voucher = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_voucher;
    }

    public function findOrFail($id)
    {
        if (null == $rendicionreceptivo_voucher = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $rendicionreceptivo_voucher;
    }

	private function guardarRendicionreceptivo_Voucher($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$rendicionreceptivo_voucher = $this->model->where('rendicionreceptivo_id', $id)->get()->pluck('id')->toArray();
			$q_rendicionreceptivo_voucher = count($rendicionreceptivo_voucher);
		}

		// Graba cuentas contables
		if (isset($data))
		{
			$voucher_ids = $data['idvouchers'];

			if ($funcion == 'update')
			{
				$_id = $rendicionreceptivo_voucher;

				// Borra los que sobran
				if ($q_rendicionreceptivo_voucher > count($voucher_ids))
				{
					for ($d = count($voucher_ids); $d < $q_rendicionreceptivo_voucher; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_rendicionreceptivo_voucher && $i < count($voucher_ids); $i++)
				{
					if ($i < count($voucher_ids))
					{
						$rendicionreceptivo_voucher = $this->model->findOrFail($_id[$i])->update([
									"rendicionreceptivo_id" => $id,
									"voucher_id" => $voucher_ids[$i]
									]);
					}
				}
				if ($q_rendicionreceptivo_voucher > count($voucher_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($voucher_ids); $i_movimiento++)
			{
				if ($voucher_ids[$i_movimiento] != '') 
				{
					$rendicionreceptivo_voucher = $this->model->create([
						"rendicionreceptivo_id" => $id,
						"voucher_id" => $voucher_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$rendicionreceptivo_voucher = $this->model->where('rendicionreceptivo_id', $id)->delete();
		}

		return $rendicionreceptivo_voucher;
	}
}
