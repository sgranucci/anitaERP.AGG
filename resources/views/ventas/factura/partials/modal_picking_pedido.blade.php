@if (\App\Support\Configuracion\EntornoEmpresaSupport::esFerli() && empty($flGeneraNotaDeCredito) && empty($flGeneraNotaDeDebito) && empty($modoNc))
<div class="modal fade" id="modalFacturaPickingPedido" tabindex="-1" role="dialog" aria-labelledby="modalFacturaPickingPedidoLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header py-2">
				<h5 class="modal-title" id="modalFacturaPickingPedidoLabel">Picking pendiente</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">
				<p class="small text-muted mb-2">L&iacute;neas de picking sin facturar del cliente de esta factura. Se pueden mezclar con art&iacute;culos y con OT. Al emitir, la l&iacute;nea queda facturada igual que en la facturaci&oacute;n de picking.</p>
				<input type="text" id="factura_picking_pedido_buscar" class="form-control form-control-sm mb-2" placeholder="Picking, pedido, art&iacute;culo, combinaci&oacute;n o lote" autocomplete="off">
				<div class="table-responsive" style="max-height: 360px;">
					<table class="table table-sm table-bordered mb-0">
						<thead style="background:#85C1E9;color:#17202A;">
							<tr>
								<th>Picking</th>
								<th>Pedido</th>
								<th>Art&iacute;culo</th>
								<th>Comb.</th>
								<th>Lote</th>
								<th class="text-right">Pares</th>
								<th>Acciones</th>
							</tr>
						</thead>
						<tbody id="factura-picking-pedido-tbody">
							<tr><td colspan="7" class="text-muted">Escrib&iacute; para buscar.</td></tr>
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
