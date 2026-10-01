@if (\App\Support\Stock\ArticuloMarketplaceGrillaSupport::uiActiva())
<script>
window.mostrarSolapaArticulo = function (numero) {
    var esMarketplace = Number(numero) === 10;
    $('.form-diseno').toggle(!esMarketplace);
    $('.form10').toggle(esMarketplace);
    var $tabs = $('#tabs-articulo');
    if ($tabs.length) {
        $tabs.find('[id^="botonform"]').removeClass('active');
        $('#botonform' + numero).addClass('active');
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/combinacion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/combinacion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/marketplace.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/marketplace.js')) ?: time() }}" type="text/javascript"></script>
<script>
$(function () {
    $(document).on('click', '#botonform1', function (e) {
        e.preventDefault();
        window.mostrarSolapaArticulo(1);
    });
    $(document).on('click', '#botonform10', function (e) {
        e.preventDefault();
        window.mostrarSolapaArticulo(10);
    });
});
</script>
@endif
