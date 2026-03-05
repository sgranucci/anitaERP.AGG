<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Conceptogasto_Cuentacontable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Conceptogasto_CuentacontableRepository implements Conceptogasto_CuentacontableRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Conceptogasto_Cuentacontable $conceptogasto_cuentacontable)
    {
        $this->model = $conceptogasto_cuentacontable;
    }

    public function all()
    {
        $conceptogasto_cuentacontable = $this->model->with('cuentacontables')->get();

		return $conceptogasto_cuentacontable;
    }

    public function leePorConceptogasto($conceptogasto_id)
    {
    	$conceptogasto_cuentacontable = $this->model->with('cuentacontables')->where('conceptogasto_id', $conceptogasto_id)->get();

		return $conceptogasto_cuentacontable;
    }

    public function leePorConceptogastoCuenta($conceptogasto_id, $cuentacontable_id)
    {
    	$conceptogasto_cuentacontable = $this->model->with('cuentacontables')->where('conceptogasto_id', $conceptogasto_id)
                                                ->where('cuentacontable_id', $cuentacontable_id)->get();

		return $conceptogasto_cuentacontable;
    }

    public function create(array $data)
    {
        $conceptogasto_cuentacontable = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $conceptogasto_cuentacontable = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$conceptogasto_cuentacontable = $this->model->find($id);

        $conceptogasto_cuentacontable = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorConceptogasto($conceptogasto_id)
    {
    	$conceptogasto_cuentacontable = $this->model->where('conceptogasto_id', $conceptogasto_id)->delete();

		return $conceptogasto_cuentacontable;
    }

    public function find($id)
    {
        if (null == $conceptogasto_cuentacontable = $this->model->with('cuentacontables')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $conceptogasto_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $conceptogasto_cuentacontable = $this->model->with('cuentacontables')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $usuario_cuentacontable;
    }

}
