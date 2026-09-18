<script>
window.CLIENTE_CONSULTA_CONTEXTO = @json($contextoPoliticaCliente ?? 'consultar');
@if (!empty($politicaCliente))
window.__clientePoliticaInicial = @json($politicaCliente);
@endif
</script>
<script>
(function () {
    if (window.clientePoliticaComercial && window.__clientePoliticaInicial) {
        window.clientePoliticaComercial.setActual(window.__clientePoliticaInicial);
    }
})();
</script>
