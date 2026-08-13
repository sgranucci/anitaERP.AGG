<script>
    window.FACTURA_URLS = window.FACTURA_URLS || {};
    window.FACTURA_URLS.preferencias = @json(route('factura_preferencias'));
</script>
<script src="{{ asset('assets/pages/scripts/ventas/preferencias_facturacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/preferencias_facturacion.js')) ?: time() }}"></script>
