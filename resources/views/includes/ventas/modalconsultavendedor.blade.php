<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultavendedorModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Vendedores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_vendedor" class="col-form-label">Buscar:</label>
              <input type="text" name="consultavendedor" id="consultavendedor" autofocus>
              <input type="hidden" name="consultavendedor_id" id="consultavendedor_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código Anita</th>
          </thead>
          <tbody id="datosvendedor"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultavendedorModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultavendedorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
