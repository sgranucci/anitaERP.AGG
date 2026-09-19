@php
    use App\Support\Stock\ArticuloFerliListadoFiltros;
    use App\Support\Stock\ArticuloEstadoCanalSupport;

    $estadoActual = $filtros['estado'] ?? ArticuloFerliListadoFiltros::ESTADO_ACTIVO;
    $canalActual = $filtros['canal'] ?? ArticuloFerliListadoFiltros::CANAL_TODOS;
    $estadoCombActual = $filtros['estado_comb'] ?? ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS;
    $baseQ = $filtrosQuery ?? [];
    $canalesFiltro = $canalesFiltro ?? collect();
    $mostrarCanal = ArticuloEstadoCanalSupport::uiFerliActiva();

    $urlEstado = function ($cod) use ($baseQ) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod === '') {
            $q['filtro_estado'] = 'TODOS';
        } elseif ($cod !== ArticuloFerliListadoFiltros::ESTADO_ACTIVO) {
            $q['filtro_estado'] = $cod;
        }

        return route('products.index', $q);
    };

    $urlCanal = function ($cod) use ($baseQ) {
        $q = $baseQ;
        unset($q['filtro_canal']);
        if ($cod !== ArticuloFerliListadoFiltros::CANAL_TODOS) {
            $q['filtro_canal'] = $cod;
        }

        return route('products.index', $q);
    };

    $urlComb = function ($cod) use ($baseQ) {
        $q = $baseQ;
        unset($q['estado_comb']);
        if ($cod !== ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS) {
            $q['estado_comb'] = $cod;
        }

        return route('products.index', $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center" data-listado-filtros-externos>
        <div class="mr-4 mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado
                @if ($mostrarCanal && $canalActual === 'LOCAL')
                    local
                @elseif ($mostrarCanal && $canalActual === 'FABRICA')
                    fábrica
                @endif
                :
            </span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
                <a href="{{ $urlEstado(ArticuloFerliListadoFiltros::ESTADO_ACTIVO) }}"
                   class="btn {{ $estadoActual === ArticuloFerliListadoFiltros::ESTADO_ACTIVO ? 'btn-success' : 'btn-outline-success' }}">
                    <i class="fa fa-check"></i> Activos
                </a>
                <a href="{{ $urlEstado(ArticuloFerliListadoFiltros::ESTADO_INACTIVO) }}"
                   class="btn {{ $estadoActual === ArticuloFerliListadoFiltros::ESTADO_INACTIVO ? 'btn-secondary' : 'btn-outline-secondary' }}">
                    Inactivos
                </a>
                <a href="{{ $urlEstado('') }}"
                   class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todos
                </a>
            </div>
        </div>
        @if ($mostrarCanal)
            <div class="mr-4 mb-1">
                <span class="text-muted small mr-2"><i class="fa fa-store"></i> Canal:</span>
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de canal">
                    @foreach ($canalesFiltro as $canal)
                        <a href="{{ $urlCanal($canal->codigo) }}"
                           class="btn {{ $canalActual === strtoupper((string) $canal->codigo) ? 'btn-warning' : 'btn-outline-warning' }}"
                           title="Art&iacute;culos con canal {{ $canal->nombre }} (el filtro Estado aplica a ese &aacute;mbito)">
                            {{ $canal->nombre }}
                        </a>
                    @endforeach
                    <a href="{{ $urlCanal(ArticuloFerliListadoFiltros::CANAL_SIN) }}"
                       class="btn {{ $canalActual === ArticuloFerliListadoFiltros::CANAL_SIN ? 'btn-secondary' : 'btn-outline-secondary' }}"
                       title="Art&iacute;culos sin canal asignado">
                        Sin canal
                    </a>
                    <a href="{{ $urlCanal(ArticuloFerliListadoFiltros::CANAL_TODOS) }}"
                       class="btn {{ $canalActual === ArticuloFerliListadoFiltros::CANAL_TODOS ? 'btn-primary' : 'btn-outline-primary' }}">
                        Todos
                    </a>
                </div>
            </div>
        @endif
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Combinaciones:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado de combinaciones">
                <a href="{{ $urlComb(ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS) }}"
                   class="btn {{ $estadoCombActual === ArticuloFerliListadoFiltros::ESTADO_COMB_ACTIVAS ? 'btn-info' : 'btn-outline-info' }}">
                    Activas
                </a>
                <a href="{{ $urlComb(ArticuloFerliListadoFiltros::ESTADO_COMB_INACTIVAS) }}"
                   class="btn {{ $estadoCombActual === ArticuloFerliListadoFiltros::ESTADO_COMB_INACTIVAS ? 'btn-info' : 'btn-outline-info' }}">
                    Inactivas
                </a>
                <a href="{{ $urlComb(ArticuloFerliListadoFiltros::ESTADO_COMB_TODOS) }}"
                   class="btn {{ $estadoCombActual === ArticuloFerliListadoFiltros::ESTADO_COMB_TODOS ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todos
                </a>
            </div>
        </div>
    </div>
</div>
