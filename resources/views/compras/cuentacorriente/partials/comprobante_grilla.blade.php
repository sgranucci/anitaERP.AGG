@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;

    $urlImpresion = ProveedorCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ProveedorCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       target="_blank"
       rel="noopener"
       class="text-primary"
       title="Ver PDF de la factura">
        {{ $etiquetaComprobante }}
    </a>
@else
    {{ $etiquetaComprobante }}
@endif
