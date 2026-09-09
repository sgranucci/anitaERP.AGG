@php
    use App\Support\Stock\ArticuloFerliListadoFiltros;

    $estadoActual = $filtros['estado_comb'] ?? ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS;
    $baseQ = $filtrosQuery ?? [];

    $urlEstado = function ($cod) use ($baseQ) {
        $q = $baseQ;
        unset($q['estado_comb']);
        if ($cod !== ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS) {
            $q['estado_comb'] = $cod;
        }

        return route('products.index', $q);
    };
@endphp
<div class="d-flex flex-wrap align-items-center">
    <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Combinaciones:</span>
    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado de combinaciones">
        <a href="{{ $urlEstado(ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS) }}"
           class="btn {{ $estadoActual === ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS ? 'btn-info' : 'btn-outline-info' }}">
            Activas
        </a>
        <a href="{{ $urlEstado(ArticuloFerliListadoFiltros::ESTADO_COMB_INACTIVAS) }}"
           class="btn {{ $estadoActual === ArticuloFerliListadoFiltros::ESTADO_COMB_INACTIVAS ? 'btn-info' : 'btn-outline-info' }}">
            Inactivas
        </a>
        <a href="{{ $urlEstado(ArticuloFerliListadoFiltros::ESTADO_COMB_TODOS) }}"
           class="btn {{ $estadoActual === ArticuloFerliListadoFiltros::ESTADO_COMB_TODOS ? 'btn-primary' : 'btn-outline-primary' }}">
            Todos
        </a>
    </div>
</div>
