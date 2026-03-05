<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultabancoModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Bancos</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_banco" class="col-form-label">Buscar:</label>
              <input type="text" name="consultabanco" id="consultabanco" autofocus>
              <input type="hidden" name="consultabanco_id" id="consultabanco_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Domicilio</th>
                <th>Localidad</th>
                <th>Teléfono</th>
                <th>E-mail</th>
              </tr>
          </thead>
          <tbody id="datosbanco"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultabancoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultabancoModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
