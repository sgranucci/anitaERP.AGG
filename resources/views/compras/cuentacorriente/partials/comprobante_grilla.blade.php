@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;

    $destinoImpresion = ProveedorCuentacorrienteGrillaSupport::destinoImpresion($data);
    $urlImpresion = ProveedorCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ProveedorCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
    $urlEdicion = ProveedorCuentacorrienteGrillaSupport::urlEdicion($data);
    $puedeEditar = ProveedorCuentacorrienteGrillaSupport::puedeEditarComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       class="text-primary js-erp-workspace"
       data-ws-modo="pdf"
       data-ws-id="cc-{{ $data->id }}"
       data-ws-titulo="{{ $destinoImpresion['titulo'] ?? 'PDF' }}"
       data-ws-meta="{{ $etiquetaComprobante }}"
       data-ws-pdf="{{ $urlImpresion }}"
       @if ($puedeEditar && $urlEdicion)
           data-ws-edit="{{ $urlEdicion }}"
       @endif
       title="{{ $destinoImpresion['titulo'] ?? 'Ver PDF' }}">
        {{ $etiquetaComprobante }}
    </a>
@else
    {{ $etiquetaComprobante }}
@endif
