<div class="modal fade" id="revertirasientoModal" role="dialog" aria-labelledby="revertirAsientoLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="revertirAsientoLabel">Revierte asiento contable</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        @csrf
    		<div class="form-group row mb-0">
   				<label for="fecha_revertir_asiento" class="col-form-label col-lg-5">Fecha del asiento de reverso</label>
          <input type="date" name="fechareverso" id="fecha_revertir_asiento" class="form-control col-lg-6" value="{{ date('Y-m-d') }}">
			</div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierrarevertirasientoModal" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" id="aceptarevertirasientoModal" class="btn btn-warning">Revertir asiento</button>
      </div>
    </div>
  </div>
</div>
