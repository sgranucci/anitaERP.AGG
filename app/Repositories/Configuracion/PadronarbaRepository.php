<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padronarba;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class PadronarbaRepository implements PadronarbaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padronarba $padronarba)
    {
        $this->model = $padronarba;
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
        if (null == $padronarba = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padronarba;
    }

    public function findOrFail($id)
    {
        if (null == $padronarba = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padronarba;
    }

    public function leePadronArba($cuit, $tipo)
	{
		// Elimino los posibles guiones
		$cuitfinal = str_replace("-", "", $cuit);

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'retperibr',
            'sistema' => 'compras',
            'campos' => '
                rpibr_retencion as retencion,
                rpibr_percepcion as percepcion
            ' , 
            'whereArmado' => " WHERE rpibr_cuit='".$cuitfinal."'"
        );
        $datas = json_decode($apiAnita->apiCall($data));
		$tasa = '';
		if (count($datas) > 0)
			$tasa = ($tipo == "percepcion" ? $datas[0]->percepcion : $datas[0]->retencion);

        return $tasa;
	}

}
