<?php

namespace App\Queries\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaEstado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sala\RequisicionSalaListadoFiltros;
use Illuminate\Support\Facades\DB;

class RequisicionSalaQuery implements RequisicionSalaQueryInterface
{
    public function __construct(
        protected RequisicionSala $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function first()
    {
        return $this->model->first();
    }

    public function leeRequisicionSala($filtros, $flPaginando = null, $withArticulos = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(RequisicionSalaListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
                'empresa_scope' => 'todas',
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = RequisicionSalaListadoFiltros::filtrosVacios();
        }

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $q = $this->model->select([
            'requisicion_sala.id as id',
            'requisicion_sala.fecha as fecha',
            'requisicion_sala.fecha_entrega as fecha_entrega',
            'requisicion_sala.numerorequisicion as numerorequisicion',
            'requisicion_sala.estado as estado',
            'requisicion_sala.comentario as comentario',
            'requisicion_sala.detalle as detalle',
            'empresa.nombre as nombreempresa',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
            'depmae.nombre as nombredeposito',
            'zona_sala.nombre as nombrezona',
            'prioridad_sala.nombre as nombreprioridad',
            'usuario.nombre as nombreusuario',
        ])
            ->join('empresa', 'empresa.id', '=', 'requisicion_sala.empresa_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'requisicion_sala.centrocosto_id')
            ->join('depmae', 'depmae.id', '=', 'requisicion_sala.deposito_id')
            ->leftJoin('zona_sala', 'zona_sala.id', '=', 'requisicion_sala.zona_sala_id')
            ->leftJoin('prioridad_sala', 'prioridad_sala.id', '=', 'requisicion_sala.prioridad_sala_id')
            ->join('usuario', 'usuario.id', '=', 'requisicion_sala.usuario_id')
            ->whereIn('requisicion_sala.empresa_id', $empresas)
            ->orderBy('requisicion_sala.id', 'desc');

        $q->selectSub(
            RequisicionSalaEstado::query()
                ->select('observacion')
                ->whereColumn('requisicion_sala_id', 'requisicion_sala.id')
                ->where('estado', self::nombreEstadoRechazada())
                ->orderByDesc('fecha')
                ->orderByDesc('id')
                ->limit(1),
            'motivo_rechazo'
        );

        RequisicionSalaListadoFiltros::aplicar($q, $filtros);

        if ($withArticulos) {
            if ($flPaginando) {
                $pag = $q->paginate(10);
                $pag->getCollection()->load(['requisicion_sala_articulos.articulos']);

                return $pag;
            }
            $coleccion = $q->get();
            $coleccion->load(['requisicion_sala_articulos.articulos']);

            return $coleccion;
        }

        return $flPaginando ? $q->paginate(10) : $q->get();
    }

    private static function nombreEstadoRechazada(): string
    {
        return RequisicionSalaEstado::$enumEstado[array_search('Z', array_column(RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
    }

    public function listadoExport($filtros)
    {
        return $this->leeRequisicionSala($filtros, false, true);
    }
}
