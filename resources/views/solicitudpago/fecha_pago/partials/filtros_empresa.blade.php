@php
    $empresaScope = $filtros['empresa_scope'] ?? 'una';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $urlEmpresa = function ($id) use ($filtros) {
        $q = [
            'consultar' => 1,
            'vencimiento_desde' => $filtros['vencimiento_desde'],
            'vencimiento_hasta' => $filtros['vencimiento_hasta'],
            'estado' => $filtros['estado'],
            'asignacion' => $filtros['asignacion'],
        ];
        if (! empty($filtros['incluir_vencidas'])) {
            $q['incluir_vencidas'] = 1;
        }
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route('programar_fecha_pago_solicitud', $q);
    };
@endphp
@if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
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
</div>
@endif
