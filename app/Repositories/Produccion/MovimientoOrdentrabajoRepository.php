<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\MovimientoOrdentrabajo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;
use DB;

class MovimientoOrdentrabajoRepository implements MovimientoOrdentrabajoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(MovimientoOrdentrabajo $movimientoordentrabajo)
    {
        $this->model = $movimientoordentrabajo;
    }

    public function estadoEnum()
    {
        return $this->model->estadoEnum();
    }

    public function all()
    {
        $tareas = [
                    config('consprod.TAREA_TERMINADA'),
                    config('consprod.TAREA_TERMINADA_STOCK'),
                    config('consprod.TAREA_FACTURADA')
                ];
        $all = $this->model->with(['ordenestrabajo_tarea' => function($query) use($tareas) {
            $query->whereIn('ordentrabajo_tarea.tarea_id', $tareas);
          }])->with('tareas')->with('operaciones')->with('empleados')->get();

        return $all;
        return $this->model->with('ordenestrabajo_tarea')->with('tareas')->with('operaciones')->with('empleados')->get();
    }

    public function allFiltrado($id = null)
    {
        $all = $this->model->select('movimientoordentrabajo.id as id',
                                    'ordentrabajo.codigo as codigo',
                                    'tarea.nombre as nombretarea',
                                    'operacion.nombre as nombreoperacion',
                                    'empleado.nombre as nombreempleado',
                                    'movimientoordentrabajo.fecha as fecha',
                                    'movimientoordentrabajo.estado as estado')
                            ->join('ordentrabajo', 'ordentrabajo.id', 'movimientoordentrabajo.ordentrabajo_id')
                            ->join('tarea', 'tarea.id', 'movimientoordentrabajo.tarea_id')
                            ->join('operacion', 'operacion.id', 'movimientoordentrabajo.operacion_id')
                            ->join('empleado', 'empleado.id', 'movimientoordentrabajo.empleado_id')
                            ->whereNotExists(function($query)
                            {
                                $query->select(DB::raw(1))
                                      ->from('ordentrabajo_tarea')
                                      ->whereRaw('ordentrabajo_tarea.ordentrabajo_id = movimientoordentrabajo.ordentrabajo_id')
                                      ->whereRaw('ordentrabajo_tarea.tarea_id in (32, 33)');
                            });

        if ($id)
            $all = $all->where('movimientoordentrabajo.ordentrabajo_id', $id);

        $all = $all->orderBy('movimientoordentrabajo.id', 'desc');

                            //->rightjoin('ordentrabajo_tarea',function ($join) {
                                //$join->on('ordentrabajo_tarea.ordentrabajo_id', 'movimientoordentrabajo.ordentrabajo_id') ;
                                //$join->whereNotIn('ordentrabajo_tarea.tarea_id', [33,32]) ;
                            //})
        
        $all = $all->paginate(800);

        return $all;
    }

    public function allSinFiltrar($id = null)
    {
        $all = $this->model->select('movimientoordentrabajo.id as id',
                                    'ordentrabajo.codigo as codigo',
                                    'tarea.nombre as nombretarea',
                                    'operacion.nombre as nombreoperacion',
                                    'empleado.nombre as nombreempleado',
                                    'movimientoordentrabajo.fecha as fecha',
                                    'movimientoordentrabajo.estado as estado')
                            ->join('ordentrabajo', 'ordentrabajo.id', 'movimientoordentrabajo.ordentrabajo_id')
                            ->join('tarea', 'tarea.id', 'movimientoordentrabajo.tarea_id')
                            ->join('operacion', 'operacion.id', 'movimientoordentrabajo.operacion_id')
                            ->join('empleado', 'empleado.id', 'movimientoordentrabajo.empleado_id');

        if ($id)
            $all = $all->where('movimientoordentrabajo.ordentrabajo_id', $id);

        $all = $all->orderBy('movimientoordentrabajo.id', 'desc')->get();

        return $all;
    }

    public function create(array $data)
    {
        $movimientoordentrabajo = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $movimientoordentrabajo = $this->model->findOrFail($id)->update($data);

		return $movimientoordentrabajo;
    }

    public function delete($id)
    {
    	$movimientoordentrabajo = MovimientoOrdenTrabajo::find($id);

        $movimientoordentrabajo = $this->model->destroy($id);

		return $movimientoordentrabajo;
    }

    public function find($id)
    {
        if (null == $movimientoordentrabajo = $this->model->with('ordenestrabajo')->with('tareas')->with('operaciones')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $movimientoordentrabajo;
    }

    public function findOrFail($id)
    {
        if (null == $movimientoordentrabajo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $movimientoordentrabajo;
    }

}
