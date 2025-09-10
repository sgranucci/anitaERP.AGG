<?php

namespace App\Repositories\Uif;

use App\Models\Uif\So_Uif;
use App\Repositories\Uif\So_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class So_UifRepository implements So_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(So_Uif $so_uif)
    {
        $this->model = $so_uif;
    }

    public function all()
    {
        $so_uif = $this->model->get();

        return $so_uif;
    }

    public function create(array $data)
    {
        $so_uif = $this->model->create($data);

        return($so_uif);
    }

    public function update(array $data, $id)
    {
        $so_uif = $this->model->findOrFail($id)->update($data);

		return $so_uif;
    }

    public function delete($id)
    {
    	$so_uif = $this->model->find($id);

        $so_uif = $this->model->destroy($id);

		return $so_uif;
    }

    public function find($id)
    {
        if (null == $so_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $so_uif;
    }

    public function findOrFail($id)
    {
        if (null == $so_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $so_uif;
    }

}
