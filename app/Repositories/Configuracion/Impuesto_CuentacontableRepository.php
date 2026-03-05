<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Impuesto_Cuentacontable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Impuesto_CuentacontableRepository implements Impuesto_CuentacontableRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Impuesto_Cuentacontable $impuesto_cuentacontable)
    {
        $this->model = $impuesto_cuentacontable;
    }

    public function all()
    {
        $impuesto_cuentacontable = $this->model->get();

		return $impuesto_cuentacontable;
    }

    public function leePorImpuesto($impuesto_id, $empresa_id = null)
    {
        if ($empresa_id)
    	    $impuesto_cuentacontable = $this->model->where('empresa_id', $empresa_id)
                                                    ->where('impuesto_id', $impuesto_id)->get();
        else
            $impuesto_cuentacontable = $this->model->where('impuesto_id', $impuesto_id)->get();

		return $impuesto_cuentacontable;
    }

    public function create(array $data)
    {
        $impuesto_cuentacontable = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $impuesto_cuentacontable = $this->model->findOrFail($id)->update($data);

        return $impuesto_cuentacontable;
    }

    public function delete($id)
    {
    	$impuesto_cuentacontable = $this->model->find($id);

        $impuesto_cuentacontable = $this->model->destroy($id);

		return $impuesto_cuentacontable;
    }

    public function deletePorImpuesto($impuesto_id)
    {
    	$impuesto_cuentacontable = $this->model->where('impuesto_id', $impuesto_id)->delete();

		return $impuesto_cuentacontable;
    }

    public function find($id)
    {
        if (null == $impuesto_cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $impuesto_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $impuesto_cuentacontable = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $impuesto_cuentacontable;
    }

}
