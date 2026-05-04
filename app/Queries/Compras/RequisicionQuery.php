<?php

namespace App\Queries\Compras;

use App\Models\Compras\Requisicion;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Auth;

class RequisicionQuery implements RequisicionQueryInterface
{
    protected $model;
    protected $empresaRepository;

    public function __construct(Requisicion $model, EmpresaRepositoryInterface $empresaRepository)
    {
        $this->model = $model;
        $this->empresaRepository = $empresaRepository;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function leeRequisicion($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        $oficina_compra_id = Auth::user()->oficinacompra_id;

        // Arma filtro de centro de costo en funcion de permisos del usuario
        $centrocosto_id = Auth::user()->centrocosto_id;

        if (can('usuario-requisicion-compras')) 
            $centrocostoFiltro = null;

        // Si tiene permiso que no es de compras solo puede ver su centro de costo
        if (can('usuario-requisicion-resto')) 
            $centrocostoFiltro = $centrocosto_id;

        $select = [
            'requisicion.id as id',
            'requisicion.fecha as fecha',
            'requisicion.numerorequisicion as numerorequisicion',
            'empresa.nombre as nombreempresa',
            'requisicion.tratamiento as tratamiento',
            'centrocosto.nombre as nombrecentrocosto',
            'requisicion.comentario as comentario',
            'requisicion.estado as estado',
            'usuario.nombre as nombreusuario',
            'requisicion.detalle as detalle',
            'proveedor.nombre as nombreproveedor',
        ];

        $q = $this->model->select($select)
            ->join('empresa', 'empresa.id', '=', 'requisicion.empresa_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->join('usuario', 'usuario.id', '=', 'requisicion.creousuario_id');

        if ($oficina_compra_id) {
            $q->where('requisicion.oficinacompra_id', $oficina_compra_id);
        }

        if ($centrocostoFiltro) {
            $q->where('requisicion.centrocosto_id', $centrocostoFiltro);
        }

        $columns = [
            ['columna' => 'requisicion.id', 'clausula' => 'LIKE'],
            ['columna' => 'empresa.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.tratamiento', 'clausula' => 'LIKE'],
            ['columna' => 'centrocosto.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.comentario', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.detalle', 'clausula' => 'LIKE'],
            ['columna' => 'requisicion.estado', 'clausula' => 'LIKE'],
            ['columna' => 'usuario.nombre', 'clausula' => 'LIKE'],
            ['columna' => 'proveedor.nombre', 'clausula' => 'LIKE'],
        ];

        if ($busqueda) {
            $q->where(function ($query) use ($busqueda, $columns) {
                foreach ($columns as $col) {
                    $query->orWhere($col['columna'], 'LIKE', '%'.$busqueda.'%');
                }
            });
        }

        $q->whereIn('requisicion.empresa_id', $empresas);

        $q->orderBy('requisicion.fecha', 'desc')->orderBy('requisicion.id', 'desc');

        if ($flPaginando) {
            return $q->paginate(10);
        }

        return $q->get();
    }
}
