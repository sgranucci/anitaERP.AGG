<?php

namespace App\Repositories\Ordenventa;

use App\Models\Ordenventa\Concepto_Cuentacontable_Ordenventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Concepto_Cuentacontable_OrdenventaRepository implements Concepto_Cuentacontable_OrdenventaRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Concepto_Cuentacontable_Ordenventa $concepto_cuentacontable_ordenventa)
    {
        $this->model = $concepto_cuentacontable_ordenventa;
    }

    public function all()
    {
        $concepto_cuentacontable_ordenventa = $this->model->get();

		return $concepto_cuentacontable_ordenventa;
    }

    public function leePorConceptoOrdenVenta($concepto_ordenventa_id, $empresa_id = null)
    {
        if ($empresa_id)
    	    $concepto_cuentacontable_ordenventa = $this->model
                                        ->where('empresa_id', $empresa_id)
                                        ->where('concepto_ordenventa_id', $concepto_ordenventa_id)->get();
        else
            $concepto_cuentacontable_ordenventa = $this->model->where('concepto_ordenventa_id', $concepto_ordenventa_id)->get();

		return $concepto_cuentacontable_ordenventa;
    }

    public function create(array $data)
    {
        $concepto_cuentacontable_ordenventa = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $concepto_cuentacontable_ordenventa = $this->model->findOrFail($id)->update($data);

        return $concepto_cuentacontable_ordenventa;
    }

    public function delete($id)
    {
    	$concepto_cuentacontable_ordenventa = $this->model->find($id);

        $concepto_cuentacontable_ordenventa = $this->model->destroy($id);

		return $concepto_cuentacontable_ordenventa;
    }

    public function deletePorConceptoOrdenVenta($concepto_ordenventa_id)
    {
    	$concepto_cuentacontable_ordenventa = $this->model->where('concepto_ordenventa_id', $concepto_ordenventa_id)->delete();

		return $concepto_cuentacontable_ordenventa;
    }

    public function find($id)
    {
        if (null == $concepto_cuentacontable_ordenventa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_cuentacontable_ordenventa;
    }

    public function findOrFail($id)
    {
        if (null == $concepto_cuentacontable_ordenventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_cuentacontable_ordenventa;
    }

}
