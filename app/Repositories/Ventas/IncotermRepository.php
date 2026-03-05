<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Incoterm;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class IncotermRepository implements IncotermRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Incoterm $incoterm)
    {
        $this->model = $incoterm;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $incoterm = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $incoterm = $this->model->findOrFail($id)->update($data);

		return $incoterm;
    }

    public function delete($id)
    {
    	$incoterm = $this->model->find($id);

        $incoterm = $this->model->destroy($id);

		return $incoterm;
    }

    public function find($id)
    {
        if (null == $incoterm = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $incoterm;
    }

    public function findOrFail($id)
    {
        if (null == $incoterm = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $incoterm;
    }
}
