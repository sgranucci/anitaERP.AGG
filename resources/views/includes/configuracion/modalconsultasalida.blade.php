<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultasalidaModal" role="dialog" aria-labelledby="consultasalidaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultasalidaModalLabel">Impresoras</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultasalida" class="col-form-label">Buscar:</label>
            <input type="text" name="consultasalida" id="consultasalida" class="form-control" autocomplete="off">
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0" id="tabla-consulta-salida">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Ubicaci&oacute;n</th>
                <th>Usos</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datossalida"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultasalidaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultasalidaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
