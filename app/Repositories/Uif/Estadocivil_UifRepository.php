<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Estadocivil_Uif;
use App\Repositories\Uif\Estadocivil_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Estadocivil_UifRepository implements Estadocivil_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Estadocivil_Uif $estadocivil_uif)
    {
        $this->model = $estadocivil_uif;
    }

    public function all()
    {
        $estadocivil_uif = $this->model->get();

        return $estadocivil_uif;
    }

    public function create(array $data)
    {
        $estadocivil_uif = $this->model->create($data);

        return($estadocivil_uif);
    }

    public function update(array $data, $id)
    {
        $estadocivil_uif = $this->model->findOrFail($id)->update($data);

		return $estadocivil_uif;
    }

    public function delete($id)
    {
    	$estadocivil_uif = $this->model->find($id);

        $estadocivil_uif = $this->model->destroy($id);

		return $estadocivil_uif;
    }

    public function find($id)
    {
        if (null == $estadocivil_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $estadocivil_uif;
    }

    public function findOrFail($id)
    {
        if (null == $estadocivil_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $estadocivil_uif;
    }

}
