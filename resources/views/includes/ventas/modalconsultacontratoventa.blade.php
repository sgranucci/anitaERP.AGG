<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacontratoventaModal" role="dialog" aria-labelledby="consultacontratoventaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacontratoventaLabel">Abonos / contratos de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultacontratoventa" class="col-form-label">Buscar:</label>
            <input type="text" name="consultacontratoventa" id="consultacontratoventa" autofocus>
          </div>
        </form>
        <table class="table table-striped table-bordered table-hover" id="tabla-consulta-contrato-venta">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Cliente</th>
              <th>Concepto</th>
              <th>Estado</th>
              <th>Empresa</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscontratoventa"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacontratoventaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacontratoventaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
