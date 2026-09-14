<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultapuntoventaModal" role="dialog" aria-labelledby="consultapuntoventaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultapuntoventaModalLabel">Puntos de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultapuntoventa" class="col-form-label">Buscar:</label>
            <input type="text" name="consultapuntoventa" id="consultapuntoventa" class="form-control" autocomplete="off" autofocus>
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0" id="tabla-consulta-puntoventa">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>C&oacute;digo</th>
                <th>Nombre</th>
                <th>Empresa</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datospuntoventa"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultapuntoventaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultapuntoventaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
