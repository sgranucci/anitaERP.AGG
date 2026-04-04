<div class="modal fade" id="aplicacionModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
<div class="modal-dialog modal-xl" role="document">
	<div class="modal-content">
		<div class="modal-header">
			<h5 class="modal-title" id="exampleModalLabel">Comprobantes aplicados</h5>
			<button type="button" class="close" data-dismiss="modal" aria-label="Close">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="modal-body">
			<form action="" method="post">
				<div class="form-group row">
					<label for="Comprobante_aplicado" class="col-form-label">Comprobante:</label>
					<input type="text" id="comprobanteaplicado" value="" readonly />
				</div>
			</form>
			<div class="form-group">
				<table class="table table-hover" id="aplicacionpedido-table">
				<thead>
					<tr>
						<th style="width: 10%;">ID</th>
						<th style="width: 15%;">Fecha aplicación</th>
						<th style="width: 19%;">Comprobante</th>
						<th style="width: 9%;">Moneda</th>
						<th style="width: 10%; text-align: right;">Cotización</th>
						<th style="width: 20%; text-align: right;">Monto aplicado</th>
						<th style="width: 20%; text-align: right;">Saldo</th>
						<th class="width80" data-orderable="false"></th>						
					</tr>
				</thead>
				<tbody id="tbody-tabla-aplicacion">     
				</tbody>       
				</table>
			</div>
			@include('compras.cuentacorriente.templateaplicacion')
		</div>
		<div class="modal-footer">
			<button type="button" id="aceptaAplicacionModal" class="btn btn-primary">Cierra</button>
		</div>
	</div>
</div>
</div>
