<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacuentaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Cuentas Contables</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_cuentacontable" class="col-form-label">Buscar:</label>
              <input type="text" name="consultacuentacontable" id="consultacuentacontable" autofocus>
              <input type="hidden" name="consultacuenta" id="consultacuenta_id">
              <input type="hidden" name="consultaempresa" id="consultaempresa_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Código</th>
              <th>Nombre</th>
          </thead>
          <tbody id="datos"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacuentaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacuentaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
