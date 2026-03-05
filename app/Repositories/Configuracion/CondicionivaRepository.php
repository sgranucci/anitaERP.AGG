<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Condicioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CondicionivaRepository implements CondicionivaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Condicioniva $condicioniva)
    {
        $this->model = $condicioniva;
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
        if (null == $condicioniva = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicioniva;
    }

    public function findOrFail($id)
    {
        if (null == $condicioniva = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicioniva;
    }
}
