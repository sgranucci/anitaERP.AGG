@php
    $empresaScope = $empresaScope ?? 'todas';
    $empresaActual = (int) ($empresaId ?? 0);
    $baseQ = $queryFiltrosCliente ?? [];
    $clienteId = $id ?? null;

    $urlEmpresa = function ($empId) use ($baseQ, $clienteId) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($empId === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $empId;
        }

        return route('listar_cuentacorriente_cliente', array_merge(['id' => $clienteId], $q));
    };
@endphp
@if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <span class="text-muted small mr-2"><i class="fa fa-building"></i> Empresa:</span>
        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de empresa">
            @foreach ($empresa_query ?? [] as $emp)
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
