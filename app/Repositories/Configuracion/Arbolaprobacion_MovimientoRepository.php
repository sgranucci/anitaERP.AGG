<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Support\Database\EloquentAuditDeleteSupport;
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
        return EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('id', $id)
        );
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
        return $this->model->where('ordenventa_id', $id)
                ->orderBy('nivel')->orderBy('id')
                ->with('enviousuarios')->with('destinatariousuarios')->get();
    }

    public function findPorRequisicion($id)
    {
        return $this->model->where('requisicion_id', $id)
                ->orderBy('nivel')->orderBy('id')
                ->with('enviousuarios')->with('destinatariousuarios')->get();
    }

    public function findPorOrdencompra($id)
    {
        return $this->model->where('ordencompra_id', $id)
            ->orderBy('nivel')->orderBy('id')
            ->with('enviousuarios')->with('destinatariousuarios')->get();
    }
}
