<div class="modal fade" id="consultalineaModal" role="dialog" aria-labelledby="consultalineaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultalineaModalLabel">L&iacute;neas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultalinea" class="col-form-label">Buscar:</label>
            <input type="text" name="consultalinea" id="consultalinea" class="form-control" autocomplete="off">
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>C&oacute;digo</th>
                <th>Nombre</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datoslinea"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultalineaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
