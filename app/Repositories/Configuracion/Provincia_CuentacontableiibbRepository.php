<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Provincia_Cuentacontableiibb;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Provincia_CuentacontableiibbRepository implements Provincia_CuentacontableiibbRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provincia_Cuentacontableiibb $provincia_cuentacontableiibb)
    {
        $this->model = $provincia_cuentacontableiibb;
    }

    public function all()
    {
        $provincia_cuentacontableiibb = $this->model->get();

		return $provincia_cuentacontableiibb;
    }

    public function leePorProvincia($provincia_id)
    {
    	$provincia_cuentacontableiibb = $this->model->where('provincia_id', $provincia_id)->get();

		return $provincia_cuentacontableiibb;
    }

    public function create(array $data)
    {
        $provincia_cuentacontableiibb = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $provincia_cuentacontableiibb = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$provincia_cuentacontableiibb = $this->model->find($id);

        $provincia_cuentacontableiibb = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorProvincia($provincia_id)
    {
    	$provincia_cuentacontableiibb = $this->model->where('provincia_id', $provincia_id)->delete();

		return $provincia_cuentacontableiibb;
    }

    public function find($id)
    {
        if (null == $provincia_cuentacontableiibb = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_cuentacontableiibb;
    }

    public function findOrFail($id)
    {
        if (null == $provincia_cuentacontableiibb = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_cuentacontableiibb;
    }

}
