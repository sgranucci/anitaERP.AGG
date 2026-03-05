<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\CondicionIIBB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CondicionIIBBRepository implements CondicionIIBBRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(CondicionIIBB $condicionIIBB)
    {
        $this->model = $condicionIIBB;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)
            ->update($data);

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $condicionIIBB = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionIIBB;
    }

    public function findOrFail($id)
    {
        if (null == $condicionIIBB = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionIIBB;
    }
}
