@php
    use App\Support\Produccion\MovimientoOrdentrabajoListadoFiltros;

    $estadoActual = $filtros['estado_tarea'] ?? MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO;
    $otId = (int) ($otId ?? ($filtros['ordentrabajo_id'] ?? 0));
    $baseQ = $filtrosQuery ?? [];
    unset($baseQ['page']);

    $urlEstado = function (string $cod) use ($baseQ, $otId) {
        $q = $baseQ;
        $q['filtro_estado_tarea'] = $cod;
        unset($q['ordentrabajo_id']);
        if ($otId > 0) {
            return route('consultamovimientoordentrabajo', ['id' => $otId] + $q);
        }

        return route('movimientoordentrabajo', $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado de tareas:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado de tareas">
            <a href="{{ $urlEstado(MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO) }}"
               class="btn {{ $estadoActual === MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO ? 'btn-success' : 'btn-outline-success' }}">
                En curso
            </a>
            <a href="{{ $urlEstado(MovimientoOrdentrabajoListadoFiltros::ESTADO_TERMINADA) }}"
               class="btn {{ $estadoActual === MovimientoOrdentrabajoListadoFiltros::ESTADO_TERMINADA ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Terminada
            </a>
            <a href="{{ $urlEstado(MovimientoOrdentrabajoListadoFiltros::ESTADO_FACTURADA) }}"
               class="btn {{ $estadoActual === MovimientoOrdentrabajoListadoFiltros::ESTADO_FACTURADA ? 'btn-info' : 'btn-outline-info' }}">
                Facturada
            </a>
            <a href="{{ $urlEstado(MovimientoOrdentrabajoListadoFiltros::ESTADO_TODAS) }}"
               class="btn {{ $estadoActual === MovimientoOrdentrabajoListadoFiltros::ESTADO_TODAS ? 'btn-primary' : 'btn-outline-primary' }}">
                Todas
            </a>
        </div>
    </div>
    <div class="text-muted small mt-1">
        En curso deja afuera las órdenes que ya tienen tarea Terminada o Facturada. Todas incluye cada movimiento.
    </div>
</div>
