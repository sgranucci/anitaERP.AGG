@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli() && empty($flGeneraNotaDeCredito) && empty($flGeneraNotaDeDebito) && empty($modoNc))
<button type="button" title="L&iacute;nea de un picking pendiente" class="btn-accion-tabla factura-abrir-picking-pedido tooltipsC">
	<i class="fa fa-list-alt"></i>
</button>
@endif
