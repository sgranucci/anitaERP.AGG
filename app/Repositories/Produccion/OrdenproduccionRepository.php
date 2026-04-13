<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Ordenproduccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class OrdenproduccionRepository implements OrdenproduccionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenproduccion $ordenproduccion)
    {
        $this->model = $ordenproduccion;
    }

    public function all()
    {
        return $this->model->orderBy('numeroordenproduccion','ASC')->get();
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
    	$ordenproduccion = Ordenproduccion::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $ordenproduccion = $this->model->destroy($id);

		return $ordenproduccion;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $ordenproduccion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenproduccion;
    }

}
