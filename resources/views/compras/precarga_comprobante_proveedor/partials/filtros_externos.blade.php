@php
    use App\Support\Compras\PrecargaComprobanteEstados;

    $estadoScope = $filtros['estado_scope'] ?? PrecargaComprobanteEstados::PENDIENTE;
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'precarga_comprobante_proveedor';

    $urlEstado = function ($estado) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['estado'], $q['estado_todas']);
        if ($estado === 'todas') {
            $q['estado_todas'] = 1;
        } elseif ($estado === PrecargaComprobanteEstados::GENERADA) {
            $q['estado'] = PrecargaComprobanteEstados::GENERADA;
        }
        // Pendiente = default sin query param

        return route($rutaIndex, $q);
    };
@endphp
<div class="card-body py-2 border-bottom bg-white">
    <div class="d-flex flex-wrap align-items-center">
        <div class="mb-1">
            <span class="text-muted small mr-2"><i class="fa fa-filter"></i> Estado:</span>
            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado de precarga">
                <a href="{{ $urlEstado(PrecargaComprobanteEstados::PENDIENTE) }}"
                   class="btn {{ $estadoScope === PrecargaComprobanteEstados::PENDIENTE ? 'btn-info' : 'btn-outline-info' }}">
                    Pendientes
                </a>
                <a href="{{ $urlEstado(PrecargaComprobanteEstados::GENERADA) }}"
                   class="btn {{ $estadoScope === PrecargaComprobanteEstados::GENERADA ? 'btn-info' : 'btn-outline-info' }}">
                    Generadas
                </a>
                <a href="{{ $urlEstado('todas') }}"
                   class="btn {{ $estadoScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas
                </a>
            </div>
        </div>
    </div>
</div>
