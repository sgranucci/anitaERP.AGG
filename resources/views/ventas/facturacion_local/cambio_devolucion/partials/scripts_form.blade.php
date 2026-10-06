<script>
window.cdmEditable = @json((bool) ($editable ?? false));
window.cdmBuscarVentaUrl = @json(route('api_buscar_venta_cambio_devolucion_marketplace'));
window.cdmVariantesUrl = @json(route('api_variantes_cambio_devolucion_marketplace', ['articuloId' => '__ID__']));
window.cdmPrecioUrl = @json(route('api_precio_cambio_devolucion_marketplace'));
</script>
@if ($editable ?? false)
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/talle/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/talle/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/color/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/color/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/form.js')) ?: time() }}"></script>
