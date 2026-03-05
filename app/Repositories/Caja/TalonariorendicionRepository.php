<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Talonariorendicion;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TalonariorendicionRepository implements TalonariorendicionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Talonariorendicion $talonariorendicion)
    {
        $this->model = $talonariorendicion;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $talonariorendicion = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $talonariorendicion = $this->model->findOrFail($id)->update($data);

		return $talonariorendicion;
    }

    public function delete($id)
    {
    	$talonariorendicion = $this->model->find($id);

        $talonariorendicion = $this->model->destroy($id);

		return $talonariorendicion;
    }

    public function find($id)
    {
        if (null == $talonariorendicion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $talonariorendicion;
    }

    public function findOrFail($id)
    {
        if (null == $talonariorendicion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $talonariorendicion;
    }
}
