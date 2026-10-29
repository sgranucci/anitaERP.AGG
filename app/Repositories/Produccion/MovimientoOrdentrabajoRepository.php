<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\MovimientoOrdentrabajo;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Produccion\MovimientoOrdentrabajoListadoColumnas;
use App\Support\Produccion\MovimientoOrdentrabajoListadoFiltros;
use Illuminate\Database\Eloquent\Builder;
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
        // Las OT terminadas o facturadas no se listan. El antijoin va adentro
        // de un límite por id descendente: si se filtra la tabla entera
        // (paginate sobre ~190 mil filas) el index tarda varios segundos.
        $tareasCerradas = [
            (int) config('consprod.TAREA_TERMINADA'),
            (int) config('consprod.TAREA_FACTURADA'),
        ];

        $cerradas = DB::table('ordentrabajo_tarea')
            ->select('ordentrabajo_id')
            ->whereIn('tarea_id', $tareasCerradas)
            ->distinct();

        $ids = DB::table('movimientoordentrabajo as m2')
            ->select('m2.id')
            ->leftJoinSub($cerradas, 'cerradas', 'cerradas.ordentrabajo_id', '=', 'm2.ordentrabajo_id')
            ->join('ordentrabajo_tarea as ott', function ($join) {
                $join->on('ott.id', '=', 'm2.ordentrabajo_tarea_id')
                    ->on('ott.ordentrabajo_id', '=', 'm2.ordentrabajo_id')
                    ->on('ott.tarea_id', '=', 'm2.tarea_id');
            })
            ->whereNull('cerradas.ordentrabajo_id');

        if ($id) {
            $ids->where('m2.ordentrabajo_id', $id);
        }

        $ids->orderByDesc('m2.id')->limit(800);

        return DB::table('movimientoordentrabajo as m')
            ->joinSub($ids, 'ids', 'ids.id', '=', 'm.id')
            ->join('ordentrabajo', 'ordentrabajo.id', '=', 'm.ordentrabajo_id')
            ->join('tarea', 'tarea.id', '=', 'm.tarea_id')
            ->join('operacion', 'operacion.id', '=', 'm.operacion_id')
            ->join('empleado', 'empleado.id', '=', 'm.empleado_id')
            ->select(
                'm.id as id',
                'ordentrabajo.codigo as codigo',
                'tarea.nombre as nombretarea',
                'operacion.nombre as nombreoperacion',
                'empleado.nombre as nombreempleado',
                'm.fecha as fecha',
                'm.estado as estado'
            )
            ->orderByDesc('m.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function leeListado(array $filtros, bool $paginar)
    {
        if (! $paginar) {
            return $this->queryListado($filtros)->get();
        }

        $perPage = (int) ($filtros['_per_page'] ?? 25);
        if ($perPage < 1) {
            $perPage = 25;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        if ($this->convienePaginarPorId($filtros)) {
            $page = $this->queryIds($filtros)->paginate($perPage);
            $ids = [];
            foreach ($page->items() as $row) {
                $ids[] = (int) $row->id;
            }
            $page->setCollection($this->hidratarFilas($ids));

            return $page;
        }

        return $this->queryListado($filtros)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesListado(array $filtros): array
    {
        $campos = MovimientoOrdentrabajoListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);

        return ListadoCortesSupport::calcular(
            $this->queryListado($filtros),
            $agrupar,
            $campos,
            'movimientoordentrabajo.id',
            [MovimientoOrdentrabajoListadoColumnas::class, 'valorCelda']
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<MovimientoOrdentrabajo>
     */
    public function queryListado(array $filtros): Builder
    {
        $query = $this->queryDetalle();
        $this->joinTareaPropia($query);

        MovimientoOrdentrabajoListadoFiltros::aplicar($query, $filtros);
        MovimientoOrdentrabajoListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }

    /**
     * Página sin texto ni orden de columnas: solo el id, para recorrer
     * el índice primario y recién después completar la fila visible.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function convienePaginarPorId(array $filtros): bool
    {
        if ($this->qbeUsaFormula($filtros)) {
            return false;
        }
        $campos = MovimientoOrdentrabajoListadoFiltros::camposOrdenables();
        if (ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos) !== []) {
            return false;
        }
        $sort = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $campos);
        if ($sort === []) {
            return true;
        }

        return count($sort) === 1 && ($sort[0]['campo'] ?? '') === 'id';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function qbeUsaFormula(array $filtros): bool
    {
        $grupos = $filtros['qbe']['grupos'] ?? [];
        if (! is_array($grupos)) {
            return false;
        }
        foreach ($grupos as $grupo) {
            foreach (($grupo['criterios'] ?? []) as $criterio) {
                if (trim((string) ($criterio['formula'] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<MovimientoOrdentrabajo>
     */
    private function queryIds(array $filtros): Builder
    {
        $query = $this->model->newQuery()->select('movimientoordentrabajo.id');
        $this->joinTareaPropia($query);
        MovimientoOrdentrabajoListadoFiltros::aplicar($query, $filtros);
        MovimientoOrdentrabajoListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }

    /**
     * @param  list<int>  $ids
     */
    private function hidratarFilas(array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        $filas = $this->queryDetalle()
            ->whereIn('movimientoordentrabajo.id', $ids)
            ->get()
            ->keyBy(fn ($row) => (int) $row->id);

        return collect($ids)
            ->map(fn (int $id) => $filas->get($id))
            ->filter()
            ->values();
    }

    /**
     * @return Builder<MovimientoOrdentrabajo>
     */
    private function queryDetalle(): Builder
    {
        return $this->model->newQuery()
            ->select(
                'movimientoordentrabajo.id as id',
                'ordentrabajo.codigo as codigo',
                'tarea.nombre as nombretarea',
                'operacion.nombre as nombreoperacion',
                'empleado.nombre as nombreempleado',
                'movimientoordentrabajo.fecha as fecha',
                'movimientoordentrabajo.estado as estado'
            )
            ->join('ordentrabajo', 'ordentrabajo.id', '=', 'movimientoordentrabajo.ordentrabajo_id')
            ->join('tarea', 'tarea.id', '=', 'movimientoordentrabajo.tarea_id')
            ->join('operacion', 'operacion.id', '=', 'movimientoordentrabajo.operacion_id')
            ->join('empleado', 'empleado.id', '=', 'movimientoordentrabajo.empleado_id');
    }

    /**
     * @param  Builder<MovimientoOrdentrabajo>  $query
     */
    private function joinTareaPropia(Builder $query): void
    {
        $query->join('ordentrabajo_tarea as ott_mov', function ($join) {
            $join->on('ott_mov.id', '=', 'movimientoordentrabajo.ordentrabajo_tarea_id')
                ->on('ott_mov.ordentrabajo_id', '=', 'movimientoordentrabajo.ordentrabajo_id')
                ->on('ott_mov.tarea_id', '=', 'movimientoordentrabajo.tarea_id');
        });
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
                            ->join('empleado', 'empleado.id', 'movimientoordentrabajo.empleado_id')
                            ->deLaTareaDeSuOt();

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
