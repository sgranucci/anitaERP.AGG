@php
    use App\Support\Sala\RequisicionSalaListadoFiltros;

    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $estadoLineaActual = (string) ($filtros['estado_linea'] ?? RequisicionSalaListadoFiltros::ESTADO_LINEA_TODOS);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consultar_requisicion_sala';

    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route($rutaIndex, $q);
    };

    $urlEstadoLinea = function ($estado) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['estado_linea']);
        if ($estado !== '' && $estado !== 'todos') {
            $q['estado_linea'] = $estado;
        }

        return route($rutaIndex, $q);
    };

    $mostrarEmpresa = ($empresa_query ?? collect())->count() > 1;
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        @if ($mostrarEmpresa)
        <div class="mb-1 mr-3">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas mis empresas
                </a>
            </div>
        </div>
        @endif
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-tags"></i> Estado ítem:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado de ítem">
                <a href="{{ $urlEstadoLinea('todos') }}"
                   class="btn {{ $estadoLineaActual === '' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Todos
                </a>
                <a href="{{ $urlEstadoLinea(RequisicionSalaListadoFiltros::ESTADO_LINEA_PENDIENTE) }}"
                   class="btn {{ $estadoLineaActual === RequisicionSalaListadoFiltros::ESTADO_LINEA_PENDIENTE ? 'btn-info' : 'btn-outline-info' }}">
                    Pendiente
                </a>
                <a href="{{ $urlEstadoLinea(RequisicionSalaListadoFiltros::ESTADO_LINEA_PARCIAL) }}"
                   class="btn {{ $estadoLineaActual === RequisicionSalaListadoFiltros::ESTADO_LINEA_PARCIAL ? 'btn-warning' : 'btn-outline-warning' }}">
                    Parcial
                </a>
                <a href="{{ $urlEstadoLinea(RequisicionSalaListadoFiltros::ESTADO_LINEA_CUMPLIDA) }}"
                   class="btn {{ $estadoLineaActual === RequisicionSalaListadoFiltros::ESTADO_LINEA_CUMPLIDA ? 'btn-success' : 'btn-outline-success' }}">
                    Cumplida
                </a>
            </div>
        </div>
    </div>
</div>
