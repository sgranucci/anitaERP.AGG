<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Tipotransaccion_CajaRepository implements Tipotransaccion_CajaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipotransaccion_Caja $tipotransaccion_caja
                                )
    {
        $this->model = $tipotransaccion_caja;
    }

    public function all($estado = null)
    {
        $tipotransaccion = $this->model;

        if ($estado)
            $tipotransaccion = $tipotransaccion->wherein('estado', $estado);
        
        return $tipotransaccion->get();
    }

    public function create(array $data)
    {
        $tipotransaccion = $this->model->create($data);

        return($tipotransaccion);
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
