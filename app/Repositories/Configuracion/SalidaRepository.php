<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Salida;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class SalidaRepository implements SalidaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'salida';
    protected $keyField = 'id';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Salida $salida)
    {
        $this->model = $salida;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $salida = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $salida = $this->model->findOrFail($id)
            ->update($data);

		return $salida;
    }

    public function delete($id)
    {
    	$salida = salida::find($id);
		
        $salida = $this->model->destroy($id);

		return $salida;
    }

    public function find($id)
    {
        if (null == $salida = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $salida;
    }

    public function findOrFail($id)
    {
        if (null == $salida = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $salida;
    }

}
