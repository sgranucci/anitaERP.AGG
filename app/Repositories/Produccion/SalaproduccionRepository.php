<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Salaproduccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class SalaproduccionRepository implements SalaproduccionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Salaproduccion $sectorsecado)
    {
        $this->model = $sectorsecado;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	$sectorsecado = Salaproduccion::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $sectorsecado = $this->model->destroy($id);

		return $sectorsecado;
    }

    public function find($id)
    {
        if (null == $sectorsecado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sectorsecado;
    }

    public function findOrFail($id)
    {
        if (null == $sectorsecado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sectorsecado;
    }

}
