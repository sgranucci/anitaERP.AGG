<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Retencion_Cobranza_Cuentacontable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Retencion_Cobranza_CuentacontableRepository implements Retencion_Cobranza_CuentacontableRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencion_Cobranza_Cuentacontable $retencion_cobranza_cuentacontable)
    {
        $this->model = $retencion_cobranza_cuentacontable;
    }

    public function all()
    {
        $retencion_cobranza_cuentacontable = $this->model->get();

		return $retencion_cobranza_cuentacontable;
    }

    public function leePorRetencion_Cobranza($retencion_cobranza_id, $empresa_id = null)
    {
        if ($empresa_id)
    	    $retencion_cobranza_cuentacontable = $this->model
                                        ->where('empresa_id', $empresa_id)
                                        ->where('retencion_cobranza_id', $retencion_cobranza_id)->get();
        else
            $retencion_cobranza_cuentacontable = $this->model->where('retencion_cobranza_id', $retencion_cobranza_id)->get();

		return $retencion_cobranza_cuentacontable;
    }

    public function create(array $data)
    {
        $retencion_cobranza_cuentacontable = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $retencion_cobranza_cuentacontable = $this->model->findOrFail($id)->update($data);

        return $retencion_cobranza_cuentacontable;
    }

    public function delete($id)
    {
    	$retencion_cobranza_cuentacontable = $this->model->find($id);

        $retencion_cobranza_cuentacontable = $this->model->destroy($id);

		return $retencion_cobranza_cuentacontable;
    }
 
    public function deletePorRetencion_Cobranza($retencion_cobranza_id)
    {
    	$retencion_cobranza_cuentacontable = $this->model->where('retencion_cobranza_id', $retencion_cobranza_id)->delete();

		return $retencion_cobranza_cuentacontable;
    }

    public function find($id)
    {
        if (null == $retencion_cobranza_cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencion_cobranza_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $retencion_cobranza_cuentacontable = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencion_cobranza_cuentacontable;
    }

}
