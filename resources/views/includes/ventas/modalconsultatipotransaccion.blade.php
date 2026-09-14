<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultatipotransaccionventaModal" role="dialog" aria-labelledby="consultatipotransaccionventaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultatipotransaccionventaModalLabel">Tipos de transacci&oacute;n (ventas)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultatipotransaccionventa" class="col-form-label">Buscar:</label>
            <input type="text" name="consultatipotransaccionventa" id="consultatipotransaccionventa" class="form-control" autocomplete="off" autofocus>
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Abreviatura</th>
                <th>Nombre</th>
                <th>Operaci&oacute;n</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datostipotransaccionventa"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatipotransaccionventaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
