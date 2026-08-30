<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaconceptoventaModal" role="dialog" aria-labelledby="consultaconceptoventaLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaconceptoventaLabel">Conceptos de venta</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultaconceptoventa" class="col-form-label">Buscar:</label>
            <input type="text" name="consultaconceptoventa" id="consultaconceptoventa" autofocus>
          </div>
        </form>
        <table class="table table-striped table-bordered table-hover" id="tabla-consulta-concepto-venta">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Descripci&oacute;n</th>
              <th>GTIN</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosconceptoventa"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaconceptoventaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaconceptoventaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
