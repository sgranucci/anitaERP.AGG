<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Concepto_Ordenventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Concepto_OrdenventaRepository implements Concepto_OrdenventaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Concepto_Ordenventa $concepto_ordenventa)
    {
        $this->model = $concepto_ordenventa;
    }

    public function all()
    {
        return $this->model->with('concepto_cuentacontable_ordenventas')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $concepto_ordenventa = $this->model->create($data);

        return $concepto_ordenventa;
    }

    public function update(array $data, $id)
    {
        $concepto_ordenventa = $this->model->findOrFail($id)
            ->update($data);

		return $concepto_ordenventa;
    }

    public function delete($id)
    {
    	$concepto_ordenventa = $this->model->find($id);

        $concepto_ordenventa = $this->model->destroy($id);

		return $concepto_ordenventa;
    }

    public function find($id)
    {
        if (null == $concepto_ordenventa = $this->model->with('concepto_cuentacontable_ordenventas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_ordenventa;
    }

    public function findOrFail($id)
    {
        if (null == $concepto_ordenventa = $this->model->with('concepto_cuentacontable_ordenventas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_ordenventa;
    }

}
