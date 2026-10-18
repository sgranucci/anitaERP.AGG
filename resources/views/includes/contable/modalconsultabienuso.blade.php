@once('anita-modal-consulta-bien-uso')
<div class="modal fade" id="consultabienusoModal" role="dialog" aria-labelledby="consultabienusoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultabienusoModalLabel">Bienes de uso</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultabienuso" class="col-form-label">Buscar:</label>
          <input type="text" name="consultabienuso" id="consultabienuso" class="form-control form-control-sm"
            placeholder="C&oacute;digo, descripci&oacute;n o hostname" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Descripci&oacute;n</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosbienuso"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
