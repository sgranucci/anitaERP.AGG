<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaproveedorModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Proveedores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_proveedor" class="col-form-label">Buscar:</label>
              <input type="text" name="consultaproveedor" id="consultaproveedor" autofocus>
              <input type="hidden" name="consultaproveedor_id" id="consultaproveedor_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Código</th>
              <th>Nombre</th>
              <th>Dirección</th>
              <th>Localidad</th>
              <th>Teléfono</th>
          </thead>
          <tbody id="datosproveedor"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaproveedorModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaproveedorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
