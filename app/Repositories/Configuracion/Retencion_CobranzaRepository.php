<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Retencion_Cobranza;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Retencion_CobranzaRepository implements Retencion_CobranzaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencion_Cobranza $retencion_cobranza)
    {
        $this->model = $retencion_cobranza;
    }

    public function all()
    {
        return $this->model->with('provincias')->with('retencion_cobranza_cuentacontables')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $retencion_cobranza = $this->model->create($data);

        return $retencion_cobranza;
    }

    public function update(array $data, $id)
    {
        $retencion_cobranza = $this->model->findOrFail($id)
            ->update($data);

		return $retencion_cobranza;
    }

    public function delete($id)
    {
    	$retencion_cobranza = $this->model->find($id);

        $retencion_cobranza = $this->model->destroy($id);

		return $retencion_cobranza;
    }

    public function find($id)
    {
        if (null == $retencion_cobranza = $this->model->with('provincias')->with('retencion_cobranza_cuentacontables')
                                        ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencion_cobranza;
    }

    public function findOrFail($id)
    {
        if (null == $retencion_cobranza = $this->model->with('provincias')->with('retencion_cobranza_cuentacontables')
                                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencion_cobranza;
    }

}
