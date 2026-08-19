@php
    use App\Support\Contable\CuentacontableListadoFiltros;
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $vistaActual = $filtros['vista'] ?? CuentacontableListadoFiltros::VISTA_ARBOL;

    $urlEmpresa = function ($id) use ($baseQ) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
            $q['vista'] = CuentacontableListadoFiltros::VISTA_LISTA;
        } else {
            $q['empresa_id'] = $id;
        }

        return route('cuentacontable', $q);
    };

    $urlVista = function ($vista) use ($baseQ, $empresaScope) {
        $q = $baseQ;
        if ($empresaScope === 'todas') {
            $vista = CuentacontableListadoFiltros::VISTA_LISTA;
        }
        $q['vista'] = $vista;

        return route('cuentacontable', $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        @if (($empresa_query ?? collect())->count() > 1)
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
            <span class="text-muted small mr-2">Vista:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Vista del plan">
                @if ($empresaScope !== 'todas')
                    <a href="{{ $urlVista(CuentacontableListadoFiltros::VISTA_ARBOL) }}"
                       class="btn {{ $vistaActual === CuentacontableListadoFiltros::VISTA_ARBOL ? 'btn-info' : 'btn-outline-info' }}">
                        <i class="fa fa-sitemap"></i> Árbol
                    </a>
                @endif
                <a href="{{ $urlVista(CuentacontableListadoFiltros::VISTA_LISTA) }}"
                   class="btn {{ $vistaActual === CuentacontableListadoFiltros::VISTA_LISTA || $empresaScope === 'todas' ? 'btn-info' : 'btn-outline-info' }}">
                    <i class="fa fa-list"></i> Lista
                </a>
            </div>
        </div>
    </div>
</div>
