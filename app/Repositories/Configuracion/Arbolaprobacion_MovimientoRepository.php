<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Arbolaprobacion_MovimientoRepository implements Arbolaprobacion_MovimientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Arbolaprobacion_Movimiento $arbolaprobacion_movimiento)
    {
        $this->model = $arbolaprobacion_movimiento;
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
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $arbolaprobacion_movimiento = $this->model->with('arbolaprobaciones')->with('ordenventas')
															->with('enviousuarios')->with('destinatariousuarios')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        } 
	}
		
	public function findOrFail($id)
    {
        if (null == $arbolaprobacion_movimiento = $this->model->with('arbolaprobaciones')->with('ordenventas')
															->with('enviousuarios')->with('destinatariousuarios')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $arbolaprobacion_movimiento;
    }

    public function findPorOrdenVenta($id)
    {
        return $this->model->where('ordenventa_id', $id)->where('deleted_at', null)
                ->with('enviousuarios')->with('destinatariousuarios')->get();
    }
}
