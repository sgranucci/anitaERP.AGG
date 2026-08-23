@php
    $depositoFacturaUi = \App\Support\Ventas\TransporteDepositoSupport::mapaAvisosUi();
@endphp
<script>
    window.DEPOSITO_FACTURA_UI = @json($depositoFacturaUi);
</script>
<script src="{{ asset('assets/pages/scripts/ventas/deposito_facturacion_aviso.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/deposito_facturacion_aviso.js')) ?: time() }}" type="text/javascript"></script>
