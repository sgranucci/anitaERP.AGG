<div class="modal fade" id="consultaempleadoModal" role="dialog" aria-labelledby="consultaempleadoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaempleadoModalLabel">Empleados de producci&oacute;n</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultaempleado" class="col-form-label col-md-2">Buscar:</label>
          <div class="col-md-10">
            <input type="text" id="consultaempleado" class="form-control" autofocus
                   placeholder="Legajo o nombre" autocomplete="off">
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped table-bordered table-hover table-sm mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th style="width:5rem;">Legajo</th>
                <th>Nombre</th>
                <th style="width:10rem;">Acciones</th>
              </tr>
            </thead>
            <tbody id="datosempleado"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
