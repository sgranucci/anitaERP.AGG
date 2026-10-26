@php
    use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;

    $urlsFactura = ClienteCuentacorrienteGrillaSupport::urlsImpresionSoloFactura((int) ($ventaId ?? 0));
@endphp
@if (! empty($urlsFactura['impresora']))
    <a href="{{ $urlsFactura['impresora'] }}"
       class="btn-accion-tabla tooltipsC"
       target="_blank"
       rel="noopener"
       title="Imprimir solo la factura">
        <i class="fa fa-print"></i>
    </a>
@endif
@if (! empty($urlsFactura['pdf']))
    <a href="{{ $urlsFactura['pdf'] }}"
       class="btn-accion-tabla tooltipsC"
       target="_blank"
       rel="noopener"
       title="PDF solo de la factura">
        <i class="fas fa-file-pdf text-danger"></i>
    </a>
@endif
