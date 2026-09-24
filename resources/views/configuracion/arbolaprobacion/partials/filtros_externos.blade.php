@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $tipoActual = (string) ($filtros['tipoarbol'] ?? '');
    $estadoActual = (string) ($filtros['estado'] ?? '');
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consulta_arbolaprobacion';
    $tiposOpciones = $tipoarbol_opciones ?? \App\Support\Configuracion\ArbolaprobacionListadoFiltros::opcionesTipoArbol();
    $estadosOpciones = $estado_opciones ?? \App\Support\Configuracion\ArbolaprobacionListadoFiltros::opcionesEstado();

    $urlConFiltro = function (array $overrides) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        foreach ($overrides as $key => $value) {
            unset($q[$key]);
            if ($value === null || $value === '') {
                continue;
            }
            $q[$key] = $value;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="anita-arbol-empresa-filter">
    @if (($empresa_query ?? collect())->count() > 1)
    <div class="d-flex flex-wrap align-items-center mb-2">
        <span class="text-muted small mr-2 mb-1"><i class="fa fa-building"></i> Empresa</span>
        <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Filtro de empresa">
            @foreach ($empresa_query as $emp)
                <a href="{{ $urlConFiltro(['empresa_id' => $emp->id, 'empresa_todas' => null]) }}"
                   class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $emp->nombre }}
                </a>
            @endforeach
            <a href="{{ $urlConFiltro(['empresa_todas' => 1, 'empresa_id' => null]) }}"
               class="btn {{ $empresaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                Todas mis empresas
            </a>
        </div>
    </div>
    @endif

    <div class="d-flex flex-wrap align-items-center mb-2">
        <span class="text-muted small mr-2 mb-1"><i class="fa fa-sitemap"></i> Tipo de orden</span>
        <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Filtro de tipo de árbol">
            <a href="{{ $urlConFiltro(['tipoarbol' => null]) }}"
               class="btn {{ $tipoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                Todos
            </a>
            @foreach ($tiposOpciones as $tipo)
                <a href="{{ $urlConFiltro(['tipoarbol' => $tipo['nombre']]) }}"
                   class="btn {{ $tipoActual === $tipo['nombre'] ? 'btn-info' : 'btn-outline-info' }}"
                   title="{{ $tipo['nombre'] }}">
                    {{ $tipo['valor'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2 mb-1"><i class="fa fa-toggle-on"></i> Estado</span>
        <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Filtro de estado">
            <a href="{{ $urlConFiltro(['estado' => null]) }}"
               class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                Todos
            </a>
            @foreach ($estadosOpciones as $est)
                <a href="{{ $urlConFiltro(['estado' => $est['nombre']]) }}"
                   class="btn {{ strcasecmp($estadoActual, $est['nombre']) === 0 ? 'btn-info' : 'btn-outline-info' }}">
                    {{ $est['nombre'] }}
                </a>
            @endforeach
        </div>
    </div>
</div>
