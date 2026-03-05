<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Empleado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class EmpleadoRepository implements EmpleadoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'empleado';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'emp_legajo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Empleado $empleado)
    {
        $this->model = $empleado;
    }

    public function all()
    {
        return $this->model->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $empleado = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $empleado = $this->model->findOrFail($id)
            ->update($data);

		return $empleado;
    }

    public function delete($id)
    {
    	$empleado = Empleado::find($id);
		
        $empleado = $this->model->destroy($id);

		return $empleado;
    }

    public function find($id)
    {
        if (null == $empleado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empleado;
    }

    public function findOrFail($id)
    {
        if (null == $empleado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $empleado;
    }

}
