<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultamovilModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Móviles</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_movil" class="col-form-label">Buscar:</label>
              <input type="text" name="consultamovil" id="consultamovil" autofocus>
              <input type="hidden" name="consultamovil_id" id="consultamovil_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código Anita</th>
              <th>Dominio</th>
              <th>Tipo de movil</th>
          </thead>
          <tbody id="datosmovil"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultamovilModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultamovilModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
