<div class="modal fade" id="revertiringresoegresoModal" role="dialog" aria-labelledby="revertirIngresoEgresoLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="revertirIngresoEgresoLabel">Revertir movimiento</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="mb-3">La reversión impacta en la fecha indicada. Si esa fecha cae en un período cerrado, no se graba.</p>
        <div class="form-group row mb-0">
          <label for="fecha-reversion-ie" class="col-form-label col-lg-5">Fecha de la reversión</label>
          <input type="date" id="fecha-reversion-ie" class="form-control col-lg-6" value="{{ date('Y-m-d') }}">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierrarevertiringresoegresoModal" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" id="aceptarevertiringresoegresoModal" class="btn btn-warning">Revertir</button>
      </div>
    </div>
  </div>
</div>
