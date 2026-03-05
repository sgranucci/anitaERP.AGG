<div class="modal fade" id="historiaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Historia de Anulaci&oacute;n del Item</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
			<div class="form-group">
            	<label for="recipient-name" class="col-form-label">Orden de trabajo</label>
            	<input type="text" style="font-weight: bold; text-align: center;" id="ordentrabajohistoria"></input>
          	</div>
          	<div class="form-group">
            	<label for="recipient-name" class="col-form-label">Cantidad m&oacute;dulos</label>
            	<input type="text" size="5" style="font-weight: bold; text-align: center;" id="canthistoriamodulo" name="cantanulacionmodulo" value="1"></input>
          	</div>
          	<div class="form-group">
            	<label for="recipient-name" class="col-form-label">Medidas</label>
            	<div id="historiaModal"></div>
          	</div>
          	<div class="form-group">
            	<label for="recipient-name" class="col-form-label">Total pares</label>
            	<input type="text" size="5" style="font-weight: bold; text-align: center;" id="tothistoriaPares" name="totanulacionPares" readonly></input>
          	</div>
			<div class="card">
				<div class="card-body">
					<table class="table table-hover" id="itemshistoria-table">
						<thead>
							<tr>
								<th>Fecha</th>
								<th>Motivo</th>
								<th>Cliente</th>
								<th>Observaci&oacute;n</th>
								<th>Estado</th>
							</tr>
						</thead>
						<tbody id="tbody-historia">
						</tbody>
					</table>
				</div>
			</div>
        </form>
      </div>
      <div class="modal-footer">
    		<button type="button" id="aceptahistoriaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
