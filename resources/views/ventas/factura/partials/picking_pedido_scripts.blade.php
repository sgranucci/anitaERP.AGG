@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
@php
    $facturaPickingConsultaUrl = route('consulta_factura_picking_pedido');
    $facturaPickingConsultarUrl = route('editar_pedido', [
        'id' => '__ID__',
        'origen' => 'modal_consulta',
        'vista' => 'consulta',
    ]);
    $facturaPickingPuedeConsultar = can('editar-pedidos', false);
@endphp
<script>
window.FACTURA_PICKING_PEDIDO = {
    consulta: @json($facturaPickingConsultaUrl),
    consultarPedido: @json($facturaPickingConsultarUrl),
    puedeConsultar: @json($facturaPickingPuedeConsultar)
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/factura/picking_pedido.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/factura/picking_pedido.js')) ?: time() }}" type="text/javascript"></script>
@endif
