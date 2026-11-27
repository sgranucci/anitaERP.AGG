@php
    use App\Support\Ventas\AplicacionCcClienteComprobanteListadoColumnas;
    use App\Support\Ventas\AplicacionCcClienteComprobanteListadoFiltros;
    $estadoActual = $filtros['estado'] ?? AplicacionCcClienteComprobanteListadoFiltros::ESTADO_DEFAULT;
    $empresaScope = $filtros['empresa_scope'] ?? 'todas';
    $empresaActual = (int) ($filtros['empresa_id'] ?? 0);
    $baseQ = $filtrosQuery ?? [];
    unset($baseQ['page']);

    $urlEstado = function (string $cod) use ($baseQ) {
        $q = $baseQ;
        unset($q['filtro_estado']);
        if ($cod === '') {
            $q['filtro_estado'] = 'TODOS';
        } elseif ($cod !== AplicacionCcClienteComprobanteListadoFiltros::ESTADO_DEFAULT) {
            $q['filtro_estado'] = $cod;
        }

        return route('aplicacion_cc_cliente', $q);
    };

    $urlEmpresa = function ($id) use ($baseQ) {
        $q = $baseQ;
        unset($q['empresa_id'], $q['empresa_todas']);
        if ($id === 'todas') {
            $q['empresa_todas'] = 1;
        } else {
            $q['empresa_id'] = $id;
        }

        return route('aplicacion_cc_cliente', $q);
    };

    $qSinCliente = $baseQ;
    unset($qSinCliente['cliente_id']);
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mr-4 mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filtro de estado">
                @foreach (AplicacionCcClienteComprobanteListadoColumnas::ESTADOS as $cod => $nombre)
                    @php
                        $activoEst = $estadoActual === $cod;
                        $clsEst = $cod === \App\Models\Ventas\AplicacionCcClienteComprobante::ESTADO_ANULADO
                            ? ($activoEst ? 'btn-danger' : 'btn-outline-danger')
                            : ($activoEst ? 'btn-success' : 'btn-outline-success');
                    @endphp
                    <a href="{{ $urlEstado($cod) }}" class="btn {{ $clsEst }}">{{ $nombre }}s</a>
                @endforeach
                <a href="{{ $urlEstado('') }}" class="btn {{ $estadoActual === '' ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
            </div>
        </div>
        @if (\App\Support\Configuracion\EmpresaFiltroExternoSupport::mostrarChips())
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
        @if ($clienteFiltro ?? null)
            <div class="mb-1">
                <span class="text-muted small mr-2"><i class="fa fa-truck"></i> Cliente:</span>
                <a href="{{ route('aplicacion_cc_cliente', $qSinCliente) }}" class="btn btn-sm btn-info" title="Quitar filtro de cliente">
                    {{ $clienteFiltro->codigo }} — {{ $clienteFiltro->nombre }} <i class="fa fa-times ml-1"></i>
                </a>
            </div>
        @endif
        <div class="mb-1 ml-auto">
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => 'lista_aplicacion_cc_cliente',
                'queryparams' => $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </div>
    </div>
</div>
