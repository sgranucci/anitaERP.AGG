<?php

namespace App\Queries\Compras;

use App\Models\Compras\Requisicion;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\RequisicionTotalesCabecera;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class RequisicionQuery implements RequisicionQueryInterface
{
    protected $model;

    protected $empresaRepository;

    protected CotizacionQueryInterface $cotizacionQuery;

    public function __construct(
        Requisicion $model,
        EmpresaRepositoryInterface $empresaRepository,
        CotizacionQueryInterface $cotizacionQuery,
    ) {
        $this->model = $model;
        $this->empresaRepository = $empresaRepository;
        $this->cotizacionQuery = $cotizacionQuery;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function leeRequisicion($busqueda, $flPaginando = null, $withArticulos = false)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        $oficina_compra_id = Auth::user()->oficinacompra_id;

        $centrocosto_id = Auth::user()->centrocosto_id;
        $centrocostoFiltro = null;

        if (can('usuario-requisicion-compras')) {
            $centrocostoFiltro = null;
        }

        if (can('usuario-requisicion-resto')) {
            $centrocostoFiltro = $centrocosto_id;
        }

        $select = [
            'requisicion.id as id',
            'requisicion.fecha as fecha',
            'requisicion.fechaentrega as fechaentrega',
            'requisicion.numerorequisicion as numerorequisicion',
            'empresa.nombre as nombreempresa',
            'requisicion.tratamiento as tratamiento',
            'requisicion.motivotratamiento as motivotratamiento',
            'requisicion.contrataciondirecta as contrataciondirecta',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'requisicion.comentario as comentario',
            'requisicion.estado as estado',
            'usuario.nombre as nombreusuario',
            'requisicion.detalle as detalle',
            'proveedor.codigo as codigoproveedor',
            'proveedor.nombre as nombreproveedor',
            'oficinacompra.nombre as nombreoficinacompra',
            'formapago.nombre as nombreformapago',
        ];

        if (Schema::hasColumn($this->model->getTable(), 'nroinscripcion')) {
            $select[] = 'requisicion.nroinscripcion as nroinscripcion';
        }

        $q = $this->model->select($select)
            ->join('empresa', 'empresa.id', '=', 'requisicion.empresa_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->leftJoin('oficinacompra', 'oficinacompra.id', '=', 'requisicion.oficinacompra_id')
            ->leftJoin('formapago', 'formapago.id', '=', 'requisicion.formapago_id')
            ->join('usuario', 'usuario.id', '=', 'requisicion.creousuario_id');

        if ($oficina_compra_id) {
            $q->where('requisicion.oficinacompra_id', $oficina_compra_id);
        }

        if ($centrocostoFiltro) {
            $q->where('requisicion.centrocosto_id', $centrocostoFiltro);
        }

        $columns = [
            ['columna' => 'requisicion.id', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.numerorequisicion', 'clausula' => 'LIKE'],
            ['columna' => 'empresa.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.tratamiento', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.motivotratamiento', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.contrataciondirecta', 'clausula' => 'LIKE'],
            ['columna' => 'centrocosto.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'centrocosto.codigo', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.comentario', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.detalle', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.estado', 'clausula' => 'LIKE'],
            ['columna' => 'usuario.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'proveedor.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'proveedor.codigo', 'clausula' => 'LIKE'],
            ['columna' => 'oficinacompra.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'formapago.nombre', 'clausula' => 'LIKE'],
        ];

        if (Schema::hasColumn($this->model->getTable(), 'nroinscripcion')) {
            $columns[] = ['columna' => 'requisicion.nroinscripcion', 'clausula' => 'LIKE'];
        }

        if ($busqueda) {
            $q->where(function ($query) use ($busqueda, $columns) {
                foreach ($columns as $col) {
                    $query->orWhere($col['columna'], 'LIKE', '%'.$busqueda.'%');
                }
            });
        }

        $q->whereIn('requisicion.empresa_id', $empresas);

        $q->orderBy('requisicion.fecha', 'desc')->orderBy('requisicion.id', 'desc');

        if ($withArticulos) {
            $q->with([
                'requisicion_articulos.articulos',
                'requisicion_articulos.monedas',
                'requisicion_articulos.centrocostos_destino',
                'requisicion_articulos.partidagastos.articulos',
                'requisicion_articulos.capexs',
            ]);
        }

        if ($flPaginando) {
            $paginator = $q->paginate(10);
            if ($withArticulos) {
                $this->enriquecerTotalesCabecera($paginator->getCollection());
            }

            return $paginator;
        }

        $collection = $q->get();
        if ($withArticulos) {
            $this->enriquecerTotalesCabecera($collection);
        }

        return $collection;
    }

    private function enriquecerTotalesCabecera(Collection $requisiciones): void
    {
        foreach ($requisiciones as $req) {
            RequisicionTotalesCabecera::aplicarAtributosVirtuales($req, $this->cotizacionQuery);
        }
    }
}
