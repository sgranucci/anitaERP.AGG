@php
    $circuitoClienteDespacho = \App\Support\Ventas\ClienteDespachoSupport::circuitoHabilitado();
    $clienteDespachoIdJs = $circuitoClienteDespacho ? \App\Support\Ventas\ClienteDespachoSupport::id() : 0;
@endphp
<script>
window.CLIENTE_DESPACHO_ID = @json($clienteDespachoIdJs);
@if (!empty($clienteDespachoNoFacturar) && $circuitoClienteDespacho)
window.CLIENTE_DESPACHO_NO_FACTURAR = true;
@endif
</script>
