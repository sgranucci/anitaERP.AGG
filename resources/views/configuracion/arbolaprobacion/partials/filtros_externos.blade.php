@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consulta_arbolaprobacion';

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
@if (($empresa_query ?? collect())->count() > 1)
<div class="anita-arbol-empresa-filter">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2 mb-1"><i class="fa fa-building"></i> Empresa</span>
        <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Filtro de empresa">
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
</div>
@endif
