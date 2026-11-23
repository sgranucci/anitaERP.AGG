@php
    $empresaScope = $filtros['empresa_scope'] ?? 'todas';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'entrega_prenda_reporte';

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

    $mostrarEmpresas = \App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips();
@endphp
<div class="oc-empresas card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        @if ($mostrarEmpresas)
        <div class="mb-1 mr-2">
            <span class="text-muted small mr-2 oc-emp-label"><i class="fa fa-building"></i> Empresa:</span>
            <div class="btn-group btn-group-sm flex-wrap oc-seg" role="group" aria-label="Filtro de empresa">
                @foreach ($empresa_query ?? [] as $emp)
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
        <div class="mb-1 mr-2">
            <span class="badge badge-info">Registros: {{ number_format((int) ($totalRegistros ?? 0), 0, ',', '.') }}</span>
            <span class="badge badge-secondary">Total unidades: {{ $fmtUnidades($totalCantidad ?? 0) }}</span>
        </div>
        <div class="mb-1 ml-auto oc-empresas-export">
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => 'listar_entrega_prenda',
                'queryparams' => $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </div>
    </div>
</div>
