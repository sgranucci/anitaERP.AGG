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
       title="Ver PDF original de la precarga">
        {{ $etiquetaComprobante }}
    </a>
@else
    {{ $etiquetaComprobante }}
@endif
