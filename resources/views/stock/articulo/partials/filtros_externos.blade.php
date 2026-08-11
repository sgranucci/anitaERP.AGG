@php
    use App\Support\Stock\ArticuloListadoFiltros;

    $estadoActual = $filtros['estado'] ?? ArticuloListadoFiltros::ESTADO_ACTIVO;
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'articulo';
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);

    $urlEstado = function ($cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod === '') {
            $q['filtro_estado'] = 'TODOS';
        } elseif ($cod !== ArticuloListadoFiltros::ESTADO_ACTIVO) {
            $q['filtro_estado'] = $cod;
        }

        return route($rutaIndex, $q);
    };

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
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-end">
    @if (ArticuloListadoFiltros::filtroEmpresaActivo() && ($empresa_query ?? collect())->count() > 1)
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
    <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Estado:</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
        <a href="{{ $urlEstado(ArticuloListadoFiltros::ESTADO_ACTIVO) }}"
           class="btn {{ $estadoActual === ArticuloListadoFiltros::ESTADO_ACTIVO ? 'btn-success' : 'btn-outline-success' }}">
            <i class="fa fa-check"></i> Activos
        </a>
        <a href="{{ $urlEstado(ArticuloListadoFiltros::ESTADO_INACTIVO) }}"
           class="btn {{ $estadoActual === ArticuloListadoFiltros::ESTADO_INACTIVO ? 'btn-secondary' : 'btn-outline-secondary' }}">
            Inactivos
        </a>
        <a href="{{ $urlEstado('') }}"
           class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
            Todos
        </a>
    </div>
</div>
