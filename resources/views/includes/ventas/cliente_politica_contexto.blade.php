<script>
window.CLIENTE_CONSULTA_CONTEXTO = @json($contextoPoliticaCliente ?? 'consultar');
window.__clienteAvisosIniciales = @json($avisosCliente ?? []);
@if (!empty($politicaCliente))
window.__clientePoliticaInicial = @json($politicaCliente);
@endif
</script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/avisos-operacion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/avisos-operacion.js')) ?: time() }}"></script>
<script>
(function () {
    if (window.clientePoliticaComercial && window.__clientePoliticaInicial) {
        window.clientePoliticaComercial.setActual(window.__clientePoliticaInicial);
    }
})();
</script>
