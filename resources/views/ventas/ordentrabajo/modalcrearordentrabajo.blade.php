<div class="modal fade" id="crearOrdenTrabajoModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Creaci&oacute;n de OT</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div class="form-group">
			<!-- textarea -->
          	<div class="form-group">
           		<label>Leyendas</label>
           		<textarea id="leyendaot" class="form-control" rows="3" placeholder="Leyendas ..."></textarea>
           	</div>
          </div>
          <div class="form-group">
    		<label>OT descontando Stock</label>
            <input id="checkotstock" class="checkboxotstock" style="margin-top:auto; margin-bottom:auto;" title="Descuenta de stock" type="checkbox" autocomplete="off">
          </div>
            <div class="col-md-4 row">
                <label style="margin-top: 6px;">C&oacute;digo de OT de Stock:</label>
                <input type="text" id="ordentrabajo_stock_codigo" name="ordentrabajo_stock_codigo" class="form-control col-sm-3" value="" />
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraOrdenTrabajoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaOrdenTrabajoModal" class="btn btn-primary">Genera OT</button>
      </div>
    </div>
  </div>
</div>
