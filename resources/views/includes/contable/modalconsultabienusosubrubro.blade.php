@once('anita-modal-consulta-bien-uso-subrubro')
<div class="modal fade" id="consultabienusosubrubroModal" role="dialog" aria-labelledby="consultabienusosubrubroModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultabienusosubrubroModalLabel">Subrubros de bienes de uso</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultabienusosubrubro" class="col-form-label">Buscar:</label>
          <input type="text" name="consultabienusosubrubro" id="consultabienusosubrubro" class="form-control form-control-sm"
            placeholder="C&oacute;digo o nombre" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Rubro</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosbienusosubrubro"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
