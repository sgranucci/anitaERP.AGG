@once('anita-modal-consulta-bien-uso-indice')
<div class="modal fade" id="consultabienusoindiceModal" role="dialog" aria-labelledby="consultabienusoindiceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultabienusoindiceModalLabel">&Iacute;ndices de actualizaci&oacute;n</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultabienusoindice" class="col-form-label">Buscar:</label>
          <input type="text" name="consultabienusoindice" id="consultabienusoindice" class="form-control form-control-sm"
            placeholder="C&oacute;digo o nombre" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosbienusoindice"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
