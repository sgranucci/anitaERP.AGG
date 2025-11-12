<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Tipoproduccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TipoproduccionRepository implements TipoproduccionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipoproduccion $tipoproduccion)
    {
        $this->model = $tipoproduccion;
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
    	$tipoproduccion = Tipoproduccion::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $tipoproduccion = $this->model->destroy($id);

		return $tipoproduccion;
    }

    public function find($id)
    {
        if (null == $tipoproduccion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoproduccion;
    }

    public function findOrFail($id)
    {
        if (null == $tipoproduccion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoproduccion;
    }

}
