<?php

namespace App\Repositories\Compras;

use App\Models\Compras\RetencionIIBB;
use App\Models\Compras\RetencionIIBB_Condicion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RetencionIIBB_CondicionRepository implements RetencionIIBB_CondicionRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(RetencionIIBB_Condicion $retencionIIBB_condicion)
    {
        $this->model = $retencionIIBB_condicion;
    }

    public function all()
    {
        $retencionesIIBB = $this->model->with("retencionesIIBB")->get();

		return $retencionesIIBB;
    }

    public function create(array $data)
    {
        $condicionpago = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $condicionpago = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$condicionpago = $this->model->find($id);

        $condicionpago = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorRetencionIIBB($retencionIIBB_id)
    {
    	$condicionpago = $this->model->where('retencionIIBB_id', $retencionIIBB_id)->delete();

		return $condicionpago;
    }

    public function find($id)
    {
        if (null == $condicionpago = $this->model->with("retencionesIIBB")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

    public function findOrFail($id)
    {
        if (null == $condicionpago = $this->model->with("retencionesIIBB")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

}
