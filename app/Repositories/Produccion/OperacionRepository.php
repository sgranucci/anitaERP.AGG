<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Operacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class OperacionRepository implements OperacionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Operacion $operacion)
    {
        $this->model = $operacion;
    }

    public function tipooperacionEnum()
    {
        return $this->model->tipooperacionEnum();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $operacion = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $operacion = $this->model->findOrFail($id)
            ->update($data);

		return $operacion;
    }

    public function delete($id)
    {
    	$operacion = Operacion::find($id);

        $operacion = $this->model->destroy($id);

		return $operacion;
    }

    public function find($id)
    {
        if (null == $operacion = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $operacion;
    }

    public function findOrFail($id)
    {
        if (null == $operacion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $operacion;
    }
}
