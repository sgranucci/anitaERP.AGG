@php
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $estadoActual = (string) ($filtros['filtro_estado'] ?? 'todos');
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'finanza_movimiento_precarga';

    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['page']);
        if ($id !== 'todas' && (int) $id > 0) {
            $q['empresa_id'] = (int) $id;
        }

        return route($rutaIndex, $q);
    };

    $urlEstado = function ($cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado'], $q['page']);
        if ($cod !== '' && $cod !== 'todos') {
            $q['filtro_estado'] = $cod;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="d-flex flex-wrap align-items-center mb-3" style="gap:1rem;">
    @if (($empresa_query ?? collect())->count() > 1)
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ $empresaActual === (int) $emp->id ? 'btn-info' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaActual === 0 ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas mis empresas
                </a>
            </div>
        </div>
    @endif
    <div class="mb-1">
        <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
        <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
            <a href="{{ $urlEstado('abierto') }}"
               class="btn {{ $estadoActual === 'abierto' ? 'btn-success' : 'btn-outline-success' }}">
                Abiertas
            </a>
            <a href="{{ $urlEstado('convertido') }}"
               class="btn {{ $estadoActual === 'convertido' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Contabilizadas
            </a>
            <a href="{{ $urlEstado('todos') }}"
               class="btn {{ $estadoActual === 'todos' || $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                Todos
            </a>
        </div>
    </div>
</div>
