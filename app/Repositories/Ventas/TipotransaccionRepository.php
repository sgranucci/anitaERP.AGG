<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Tipotransaccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TipotransaccionRepository implements TipotransaccionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipotransaccion $tipotransaccion)
    {
        $this->model = $tipotransaccion;
    }

    public function all($operacion, $estado = null)
    {
        $tipotransaccion = $this->model;

        if ($operacion && $operacion != '*')
            $tipotransaccion = $tipotransaccion->wherein('operacion', $operacion);

        if ($estado)
            $tipotransaccion = $tipotransaccion->wherein('estado', $estado);
        
        return $tipotransaccion->get();
    }

    public function create(array $data)
    {
        $tipotransaccion = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $tipotransaccion = $this->model->findOrFail($id)->update($data);

		return $tipotransaccion;
    }

    public function delete($id)
    {
    	$tipotransaccion = $this->model->find($id);

        $tipotransaccion = $this->model->destroy($id);

		return $tipotransaccion;
    }

    public function find($id)
    {
        if (null == $tipotransaccion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion;
    }

    public function findOrFail($id)
    {
        if (null == $tipotransaccion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipotransaccion;
    }
}
