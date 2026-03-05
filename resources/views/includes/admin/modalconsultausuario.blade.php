<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultausuarioModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Usuarios</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_usuario" class="col-form-label">Buscar:</label>
              <input type="text" name="consultausuario" id="consultausuario" autofocus>
              <input type="hidden" name="consultausuario_id" id="consultausuario_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Centro de Costo</th>
          </thead>
          <tbody id="datosusuario"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultausuarioModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultausuarioModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
