@php
    $estadoActual = $filtros['estado'] ?? 'A';
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $ccActual = (int) ($filtros['centrocosto_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'bien_uso';

    $urlEstado = function ($cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod === '') {
            $q['filtro_estado'] = 'TODOS';
        } elseif ($cod !== 'A') {
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

    $urlCc = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_centrocosto_id']);
        if ($id > 0) {
            $q['filtro_centrocosto_id'] = $id;
        }

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mr-4 mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
                <a href="{{ $urlEstado('A') }}"
                   class="btn {{ $estadoActual === 'A' ? 'btn-success' : 'btn-outline-success' }}">
                    Activos
                </a>
                @foreach (($estado_enum ?? []) as $est)
                    @if (($est['valor'] ?? '') === 'A')
                        @continue
                    @endif
                    <a href="{{ $urlEstado($est['valor']) }}"
                       class="btn {{ $estadoActual === ($est['valor'] ?? '') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                        {{ $est['nombre'] ?? $est['valor'] }}
                    </a>
                @endforeach
                <a href="{{ $urlEstado('') }}"
                   class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todos
                </a>
            </div>
        </div>
        @if (($empresa_query ?? collect())->count() > 0)
            <div class="mr-4 mb-1">
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
        @if (($centrocosto_opciones ?? collect())->count() > 0 && ! ($filtro_centrocosto_restringido ?? false))
            <div class="mb-1">
                <span class="text-muted small mr-2"><i class="fa fa-sitemap"></i> Centro de costo:</span>
                <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de centro de costo">
                    <a href="{{ $urlCc(0) }}"
                       class="btn {{ $ccActual === 0 ? 'btn-primary' : 'btn-outline-primary' }}">
                        Todos
                    </a>
                    @foreach ($centrocosto_opciones as $cc)
                        <a href="{{ $urlCc((int) $cc->id) }}"
                           class="btn {{ $ccActual === (int) $cc->id ? 'btn-info' : 'btn-outline-info' }}">
                            {{ $cc->codigo }} — {{ $cc->nombre }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
