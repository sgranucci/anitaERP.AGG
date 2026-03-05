<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Caja_Asignacion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Caja_AsignacionRepository implements Caja_AsignacionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Caja_Asignacion $caja
                                )
    {
        $this->model = $caja;
    }

    public function all($estado = null)
    {
        $caja = $this->model;

        if ($estado)
            $caja = $caja->wherein('estado', $estado);
        
        return $caja->orderBy('id', 'desc')->get();
    }

    public function create(array $data)
    {
        $caja = $this->model->create($data);

        return($caja);
    }

    public function update(array $data, $id)
    {
        $caja = $this->model->findOrFail($id)->update($data);

		return $caja;
    }

    public function delete($id)
    {
    	$caja = $this->model->find($id);

        $caja = $this->model->destroy($id);

		return $caja;
    }

    public function find($id)
    {
        if (null == $caja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja;
    }

    public function findOrFail($id)
    {
        if (null == $caja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $caja;
    }

}
