@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
@php
    $facturaOtConsultaUrl = route('consulta_factura_ot_pedido');
    $facturaOtConsultarUrl = route('editar_ordentrabajo', [
        'id' => '__ID__',
        'origen' => 'modal_consulta',
        'vista' => 'consulta',
    ]);
    $facturaOtPuedeConsultar = can('editar-ordenes-de-trabajo', false);
@endphp
<script>
window.FACTURA_OT_PEDIDO = {
    consulta: @json($facturaOtConsultaUrl),
    consultarOt: @json($facturaOtConsultarUrl),
    puedeConsultar: @json($facturaOtPuedeConsultar)
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/factura/ot_pedido.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/factura/ot_pedido.js')) ?: time() }}" type="text/javascript"></script>
@endif
