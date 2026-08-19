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
        } elseif ($estado === PrecargaComprobanteEstados::CARGADA_ANITA) {
            $q['estado'] = PrecargaComprobanteEstados::CARGADA_ANITA;
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
                <a href="{{ $urlEstado(PrecargaComprobanteEstados::CARGADA_ANITA) }}"
                   class="btn {{ $estadoScope === PrecargaComprobanteEstados::CARGADA_ANITA ? 'btn-info' : 'btn-outline-info' }}">
                    Ya cargadas en Anita
                </a>
                <a href="{{ $urlEstado('todas') }}"
                   class="btn {{ $estadoScope === 'todas' ? 'btn-primary' : 'btn-outline-primary' }}">
                    Todas
                </a>
            </div>
            @if ($estadoScope === PrecargaComprobanteEstados::PENDIENTE && can('editar-precarga-proveedores', false))
            <form action="{{ route('detectar_precargas_comprobante_proveedor_cargadas_anita') }}"
                  method="POST"
                  class="d-inline ml-2"
                  onsubmit="return confirm('Se consultará Anita (hasta 40 pendientes) y se marcarán las facturas que ya existan en compra. ¿Continuar?');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Marca las pendientes que ya existen en Anita">
                    <i class="fa fa-search"></i> Detectar ya cargadas en Anita
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
