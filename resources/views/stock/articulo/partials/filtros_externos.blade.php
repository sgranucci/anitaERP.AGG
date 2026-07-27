@php
    use App\Support\Stock\ArticuloListadoFiltros;

    $estadoActual = $filtros['estado'] ?? ArticuloListadoFiltros::ESTADO_ACTIVO;
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'articulo';

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
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-end">
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
