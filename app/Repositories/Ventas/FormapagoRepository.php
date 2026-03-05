<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Formapago;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FormapagoRepository implements FormapagoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Formapago $formapago)
    {
        $this->model = $formapago;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $formapago = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $formapago = $this->model->findOrFail($id)->update($data);

		return $formapago;
    }

    public function delete($id)
    {
    	$formapago = $this->model->find($id);

        $formapago = $this->model->destroy($id);

		return $formapago;
    }

    public function find($id)
    {
        if (null == $formapago = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $formapago;
    }

    public function findOrFail($id)
    {
        if (null == $formapago = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $formapago;
    }

    public function findPorAbreviatura($abreviatura)
    {
        if (null == $formapago = $this->model->where('abreviatura', $abreviatura)->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $formapago;
    }

}
