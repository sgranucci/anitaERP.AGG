<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Puntaje_Uif;
use App\Repositories\Uif\Puntaje_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Puntaje_UifRepository implements Puntaje_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Puntaje_Uif $puntaje_uif)
    {
        $this->model = $puntaje_uif;
    }

    public function all()
    {
        $puntaje_uif = $this->model->get();

        return $puntaje_uif;
    }

    public function create(array $data)
    {
        $puntaje_uif = $this->model->create($data);

        return($puntaje_uif);
    }

    public function update(array $data, $id)
    {
        $puntaje_uif = $this->model->findOrFail($id)->update($data);

		return $puntaje_uif;
    }

    public function delete($id)
    {
    	$puntaje_uif = $this->model->find($id);

        $puntaje_uif = $this->model->destroy($id);

		return $puntaje_uif;
    }

    public function find($id)
    {
        if (null == $puntaje_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntaje_uif;
    }

    public function findOrFail($id)
    {
        if (null == $puntaje_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntaje_uif;
    }

}
