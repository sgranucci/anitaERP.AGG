<div class="modal fade" id="copiarasientoModal" role="dialog" aria-labelledby="copiarAsientoLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info">
        <h5 class="modal-title" id="copiarAsientoLabel">Copia de asiento contable</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        @csrf
    		<div class="form-group row mb-0">
   				<label for="fecha_copiar_asiento" class="col-form-label col-lg-5">Fecha del asiento copiado</label>
          <input type="date" name="fechacopia" id="fecha_copiar_asiento" class="form-control col-lg-6" value="{{ date('Y-m-d') }}">
			</div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierracopiarasientoModal" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" id="aceptacopiarasientoModal" class="btn btn-info">Copiar asiento</button>
      </div>
    </div>
  </div>
</div>
