@php
    use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

    $destinoImpresion = ClienteCuentacorrienteGrillaSupport::destinoImpresion($data);
    $urlImpresion = ClienteCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ClienteCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
    $urlEdicion = ClienteCuentacorrienteGrillaSupport::urlEdicion($data);
    $puedeEditar = ClienteCuentacorrienteGrillaSupport::puedeEditarComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       class="text-primary js-erp-workspace"
       data-ws-modo="pdf"
       data-ws-id="cc-{{ $data->id }}"
       data-ws-titulo="{{ $destinoImpresion['titulo'] ?? 'Imprimir comprobante' }}"
       data-ws-meta="{{ $etiquetaComprobante }}"
       data-ws-pdf="{{ $urlImpresion }}"
       @if ($puedeEditar && $urlEdicion)
           data-ws-edit="{{ $urlEdicion }}"
       @endif
       title="{{ $destinoImpresion['titulo'] ?? 'Imprimir comprobante' }}">
        {{ $etiquetaComprobante }}
    </a>
@else
    {{ $etiquetaComprobante }}
@endif
@if ($data->venta_id > 0 && ! empty($data->ventas->lugarentrega))
    <br><small class="text-muted">Entrega: {{ $data->ventas->lugarentrega }}</small>
@endif
