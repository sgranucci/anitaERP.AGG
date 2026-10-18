@php
    use App\Support\Configuracion\EntornoEmpresaSupport;

    $esCrown = EntornoEmpresaSupport::esCrown();
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $salaScope = $filtros['sala_scope'] ?? 'todas';
    $salaActual = (int) ($filtros['sala_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = $rutaIndex ?? 'consultar_requisicion';

    $urlEmpresa = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas'], $q['page']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route($rutaIndex, $q);
    };

    $urlSala = function ($id) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['sala_id'], $q['sala_todas'], $q['page']);
        if ($id === 'todas') {
            $q['sala_todas'] = 1;
        } else {
            $q['sala_id'] = $id;
        }

        return route($rutaIndex, $q);
    };

    // Todas las instalaciones: chips de empresa solo con >1 asignada en sesión.
    $mostrarEmpresas = \App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips();
    $mostrarSalas = $esCrown && ($sala_query ?? collect())->count() > 0;
    $mostrarExport = ! empty($exportRuta);
@endphp
@if ($mostrarEmpresas || $mostrarSalas || $mostrarExport)
<div class="oc-empresas card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        @if ($mostrarSalas)
        <div class="mb-1 mr-2">
            <span class="text-muted small mr-2 oc-emp-label"><i class="fa fa-map-marker"></i> Sala:</span>
            <div class="btn-group btn-group-sm flex-wrap oc-seg" role="group" aria-label="Filtro de sala">
                @foreach ($sala_query as $sala)
                    <a href="{{ $urlSala($sala->id) }}"
                       class="btn {{ ($salaScope !== 'todas' && $salaActual === (int) $sala->id) ? 'btn-info oc-activo' : 'btn-outline-info' }}">
                        {{ $sala->codigo }}
                    </a>
                @endforeach
                <a href="{{ $urlSala('todas') }}"
                   class="btn {{ $salaScope === 'todas' ? 'btn-primary oc-activo' : 'btn-outline-primary' }}">
                    Todas las salas
                </a>
            </div>
        </div>
        @endif
        @if ($mostrarEmpresas)
        <div class="mb-1 mr-2">
            <span class="text-muted small mr-2 oc-emp-label"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap oc-seg" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query as $emp)
                    <a href="{{ $urlEmpresa($emp->id) }}"
                       class="btn {{ ($empresaScope !== 'todas' && $empresaActual === (int) $emp->id) ? 'btn-info oc-activo' : 'btn-outline-info' }}">
                        {{ $emp->nombre }}
                    </a>
                @endforeach
                <a href="{{ $urlEmpresa('todas') }}"
                   class="btn {{ $empresaScope === 'todas' ? 'btn-primary oc-activo' : 'btn-outline-primary' }}">
                    Todas mis empresas
                </a>
            </div>
        </div>
        @endif
        @if ($mostrarExport)
        <div class="mb-1 ml-auto oc-empresas-export">
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => $exportRuta,
                'queryparams' => $exportQueryparams ?? $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </div>
        @endif
    </div>
</div>
@endif
