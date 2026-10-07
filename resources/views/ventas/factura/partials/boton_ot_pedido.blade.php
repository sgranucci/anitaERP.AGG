@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli() && empty($flGeneraNotaDeCredito) && empty($flGeneraNotaDeDebito) && empty($modoNc))
<button type="button" title="OT de un pedido" class="btn-accion-tabla factura-abrir-ot-pedido tooltipsC">
	<i class="fa fa-hashtag"></i>
</button>
@endif
