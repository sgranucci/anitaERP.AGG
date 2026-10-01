@if (\App\Support\Stock\ArticuloMarketplaceGrillaSupport::uiActiva())
@include('includes.ventas.modalconsultamarketplace')
@include('includes.stock.modalconsultacombinacion')
<input type="hidden" id="articulo-marketplace-consulta-url" value="{{ route('consulta_marketplace') }}">
<input type="hidden" id="articulo-marketplace-resolver-url" value="{{ route('resolver_marketplace') }}">
<script>
window.otCombinacionesLista = @json($articuloCombinacionesLista ?? []);
</script>
@endif
