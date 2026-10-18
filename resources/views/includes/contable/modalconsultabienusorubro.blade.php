@once('anita-modal-consulta-bien-uso-rubro')
<div class="modal fade" id="consultabienusorubroModal" role="dialog" aria-labelledby="consultabienusorubroModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultabienusorubroModalLabel">Rubros de bienes de uso</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultabienusorubro" class="col-form-label">Buscar:</label>
          <input type="text" name="consultabienusorubro" id="consultabienusorubro" class="form-control form-control-sm"
            placeholder="C&oacute;digo o nombre" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosbienusorubro"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
