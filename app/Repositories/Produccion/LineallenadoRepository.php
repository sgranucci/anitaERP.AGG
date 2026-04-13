<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Lineallenado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class LineallenadoRepository implements LineallenadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Lineallenado $lineallenado)
    {
        $this->model = $lineallenado;
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
    	$lineallenado = Lineallenado::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $lineallenado = $this->model->destroy($id);

		return $lineallenado;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $lineallenado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $lineallenado;
    }

}
