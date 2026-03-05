<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Retencionganancia;
use App\Models\Compras\Retencionganancia_Escala;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Retencionganancia_EscalaRepository implements Retencionganancia_EscalaRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencionganancia_Escala $retencionganancia_escala)
    {
        $this->model = $retencionganancia_escala;
    }

    public function all()
    {
        $retencionesganancia = $this->model->with("retencionesganancia")->get();

		return $retencionesganancia;
    }

    public function create(array $data)
    {
        $retencionganancia = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $retencionganancia = $this->model->findOrFail($id)->update($data);

        return $retencionganancia;
    }

    public function delete($id)
    {
    	$retencionganancia = $this->model->find($id);

        $retencionganancia = $this->model->destroy($id);

		return $retencionganancia;
    }

    public function deletePorRetencionGanancia($retencionganancia_id)
    {
    	$retencionganancia = $this->model->where('retencionganancia_id', $retencionganancia_id)->delete();

		return $retencionganancia;
    }

    public function find($id)
    {
        if (null == $retencionganancia = $this->model->with("retencionesganancia")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionganancia;
    }

    public function findOrFail($id)
    {
        if (null == $retencionganancia = $this->model->with("retencionesganancia")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionganancia;
    }

}
