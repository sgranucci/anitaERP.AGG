@php
    use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

    $urlImpresion = ClienteCuentacorrienteGrillaSupport::urlImpresion($data);
    $puedeImprimir = ClienteCuentacorrienteGrillaSupport::puedeImprimirComprobante($data);
@endphp
@if ($puedeImprimir && $urlImpresion)
    <a href="{{ $urlImpresion }}"
       target="_blank"
       rel="noopener"
       class="text-primary"
       title="Imprimir comprobante">
        {{ $etiquetaComprobante }}
    </a>
@else
    {{ $etiquetaComprobante }}
@endif
@if ($data->venta_id > 0 && ! empty($data->ventas->lugarentrega))
    <br><small class="text-muted">Entrega: {{ $data->ventas->lugarentrega }}</small>
@endif
