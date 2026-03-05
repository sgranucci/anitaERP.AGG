<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Pep_Uif;
use App\Repositories\Uif\Pep_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Pep_UifRepository implements Pep_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pep_Uif $pep_uif)
    {
        $this->model = $pep_uif;
    }

    public function all()
    {
        $pep_uif = $this->model->get();

        return $pep_uif;
    }

    public function create(array $data)
    {
        $pep_uif = $this->model->create($data);

        return($pep_uif);
    }

    public function update(array $data, $id)
    {
        $pep_uif = $this->model->findOrFail($id)->update($data);

		return $pep_uif;
    }

    public function delete($id)
    {
    	$pep_uif = $this->model->find($id);

        $pep_uif = $this->model->destroy($id);

		return $pep_uif;
    }

    public function find($id)
    {
        if (null == $pep_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pep_uif;
    }

    public function findOrFail($id)
    {
        if (null == $pep_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pep_uif;
    }

}
