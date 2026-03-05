<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Factorriesgo_Uif;
use App\Repositories\Uif\Factorriesgo_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Factorriesgo_UifRepository implements Factorriesgo_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Factorriesgo_Uif $factorriesgo_uif)
    {
        $this->model = $factorriesgo_uif;
    }

    public function all()
    {
        $factorriesgo_uif = $this->model->get();

        return $factorriesgo_uif;
    }

    public function create(array $data)
    {
        $factorriesgo_uif = $this->model->create($data);

        return($factorriesgo_uif);
    }

    public function update(array $data, $id)
    {
        $factorriesgo_uif = $this->model->findOrFail($id)->update($data);

		return $factorriesgo_uif;
    }

    public function delete($id)
    {
    	$factorriesgo_uif = $this->model->find($id);

        $factorriesgo_uif = $this->model->destroy($id);

		return $factorriesgo_uif;
    }

    public function find($id)
    {
        if (null == $factorriesgo_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $factorriesgo_uif;
    }

    public function findOrFail($id)
    {
        if (null == $factorriesgo_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $factorriesgo_uif;
    }

}
