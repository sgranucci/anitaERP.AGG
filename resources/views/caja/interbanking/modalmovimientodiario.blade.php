<div class="modal fade" id="movimientodiarioModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Movimientos de Cuentas Interbanking</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          	<div class="form-group">
          	</div>
			<div class="card">
				<div class="card-body">
					<table class="table table-hover" id="itemsmovimientodiario-table">
						<thead>
							<tr>
                            <th style="width: 12%;">Fecha</th>
                            <th style="width: 12%; text-align: right;">Débitos</th>
                            <th style="width: 12%; text-align: right;">Créditos</th>
                            <th style="width: 12%; text-align: right;">Saldo</th>
							</tr>
						</thead>
						<tbody id="tbody-movimientodiario">
						</tbody>
					</table>
				</div>
			</div>
        </form>
      </div>
      <div class="modal-footer">
    		<button type="button" id="aceptamovimientodiarioModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
