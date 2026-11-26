@once('anita-modal-consulta-grupo-centrocosto')
<div class="modal fade" id="consultagrupoccModal" role="dialog" aria-labelledby="consultagrupoccModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultagrupoccModalLabel">Grupos de centros de costo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultagrupocc" class="col-form-label">Buscar:</label>
          <input type="text" name="consultagrupocc" id="consultagrupocc" class="form-control form-control-sm"
            placeholder="C&oacute;digo o nombre del grupo" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
              <th>Grupo</th>
              <th>Nombre</th>
              <th class="text-right">Centros</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosgrupocc"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
