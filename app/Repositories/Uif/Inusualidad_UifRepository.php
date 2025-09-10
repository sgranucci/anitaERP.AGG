<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Inusualidad_Uif;
use App\Repositories\Uif\Inusualidad_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Inusualidad_UifRepository implements Inusualidad_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Inusualidad_Uif $inusualidad_uif)
    {
        $this->model = $inusualidad_uif;
    }

    public function all()
    {
        $inusualidad_uif = $this->model->get();

        return $inusualidad_uif;
    }

    public function create(array $data)
    {
        $inusualidad_uif = $this->model->create($data);

        return($inusualidad_uif);
    }

    public function update(array $data, $id)
    {
        $inusualidad_uif = $this->model->findOrFail($id)->update($data);

		return $inusualidad_uif;
    }

    public function delete($id)
    {
    	$inusualidad_uif = $this->model->find($id);

        $inusualidad_uif = $this->model->destroy($id);

		return $inusualidad_uif;
    }

    public function find($id)
    {
        if (null == $inusualidad_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $inusualidad_uif;
    }

    public function findOrFail($id)
    {
        if (null == $inusualidad_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $inusualidad_uif;
    }

}
