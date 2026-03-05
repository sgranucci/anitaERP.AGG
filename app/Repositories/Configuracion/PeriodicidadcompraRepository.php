<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Periodicidadcompra;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class PeriodicidadcompraRepository implements PeriodicidadcompraRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Periodicidadcompra $periodicidadcompra)
    {
        $this->model = $periodicidadcompra;
    }

    public function all()
    {
        return $this->model->orderBy('id','ASC')->get();
    }

    public function create(array $data)
    {
        $periodicidadcompra = $this->model->create($data);

        return ($periodicidadcompra);
    }

    public function update(array $data, $id)
    {
        $periodicidadcompra = $this->model->findOrFail($id)
            ->update($data);
		
		return $periodicidadcompra;
    }

    public function delete($id)
    {
        $periodicidadcompra = $this->model->destroy($id);

		return $periodicidadcompra;
    }

    public function find($id)
    {
        if (null == $periodicidadcompra = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $periodicidadcompra;
    }

    public function findOrFail($id)
    {
        if (null == $periodicidadcompra = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $periodicidadcompra;
    }

}
