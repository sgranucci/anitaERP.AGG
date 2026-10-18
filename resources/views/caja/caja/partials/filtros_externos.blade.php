@php
    $mostrarSala = \App\Support\Configuracion\EntornoEmpresaSupport::esCrown()
        && ($sala_query ?? collect())->count() > 0;
    $salaScope = $filtros['sala_scope'] ?? 'todas';
    $salaActual = (int) ($filtros['sala_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consulta_caja';

    $urlSala = function ($modo, $id = null) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['sala_id'], $q['sala_todas'], $q['sala_sin'], $q['sala_scope'], $q['page']);
        if ($modo === 'todas') {
            $q['sala_todas'] = 1;
        } elseif ($modo === 'sin') {
            $q['sala_sin'] = 1;
        } else {
            $q['sala_id'] = $id;
        }

        return route($rutaIndex, $q);
    };
@endphp
@if ($mostrarSala)
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-map-marker-alt"></i> Sala:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de sala">
                @foreach ($sala_query as $sala)
                    <a href="{{ $urlSala('una', $sala->id) }}"
                       class="btn {{ ($salaScope === 'una' && $salaActual === (int) $sala->id) ? 'btn-info' : 'btn-outline-info' }}"
                       title="{{ $sala->nombre }}">
                        {{ $sala->codigo }}
                    </a>
                @endforeach
                <a href="{{ $urlSala('sin') }}"
                   class="btn {{ $salaScope === 'sin' ? 'btn-warning' : 'btn-outline-warning' }}">
                    Sin sala
                </a>
                <a href="{{ $urlSala('todas') }}"
                   class="btn {{ $salaScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas
                </a>
            </div>
        </div>
    </div>
</div>
@endif
