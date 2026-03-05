<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padroncaba;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;

class PadroncabaRepository implements PadroncabaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padroncaba $padroncaba)
    {
        $this->model = $padroncaba;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)
            ->update($data);

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $padroncaba = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padroncaba;
    }

    public function findOrFail($id)
    {
        if (null == $padroncaba = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padroncaba;
    }

    public function leePadronCaba($cuit, $tipo)
	{
		// Elimino los posibles guiones
		$cuitfinal = str_replace("-", "", $cuit);

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');
		$desde_fecha = substr($fecha, 0, 6)."01";
		$hasta_fecha = substr($fecha, 0, 6)."31";

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
            'sistema' => 'compras',
			'tabla' => 'retpercaba',
            'campos' => '
                rpcaba_retencion as retencion,
                rpcaba_percepcion as percepcion
            ' , 
            'whereArmado' => " WHERE rpcaba_cuit='".$cuitfinal."' and rpcaba_desde_fecha>=".
				$desde_fecha." AND rpcaba_hasta_fecha<=".$hasta_fecha
        );
        $datas = json_decode($apiAnita->apiCall($data));
		$tasa = '';
		if (count($datas) > 0)
			$tasa = ($tipo == "percepcion" ? $datas[0]->percepcion : $datas[0]->retencion);

		return $tasa;
	}
}
