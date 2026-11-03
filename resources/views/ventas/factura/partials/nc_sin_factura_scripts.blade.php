@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli())
@php
	$ncSinFacturaModuloId = \App\Support\Ventas\Ferli\NotaCreditoSinFacturaStockFerliSupport::MODULO_ABIERTO_ID;
@endphp
<script>
window.FACTURA_NC_SIN_FACTURA = {
	activo: true,
	moduloAbiertoId: @json($ncSinFacturaModuloId)
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/factura/nc_sin_factura.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/factura/nc_sin_factura.js')) ?: time() }}" type="text/javascript"></script>
@endif
