@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli() && empty($flGeneraNotaDeCredito) && empty($flGeneraNotaDeDebito) && empty($modoNc))
<div class="modal fade" id="modalFacturaOtPedido" tabindex="-1" role="dialog" aria-labelledby="modalFacturaOtPedidoLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title" id="modalFacturaOtPedidoLabel">OT de un pedido</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="small text-muted mb-2">OT terminadas y sin facturar del cliente de esta factura. Se pueden mezclar pedidos en el mismo comprobante.</p>
				<input type="text" id="factura_ot_pedido_buscar" class="form-control form-control-sm mb-2" placeholder="Pedido, OT, art&iacute;culo o combinaci&oacute;n" autocomplete="off">
				<div class="table-responsive" style="max-height: 360px;">
					<table class="table table-sm table-bordered mb-0">
						<thead style="background:#85C1E9;color:#17202A;">
							<tr>
								<th>Pedido</th>
								<th>OT</th>
								<th>Art&iacute;culo</th>
								<th>Comb.</th>
								<th class="text-right">Pares</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="factura-ot-pedido-tbody">
							<tr><td colspan="6" class="text-muted">Escrib&iacute; para buscar.</td></tr>
						</tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer py-2">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>
@endif
