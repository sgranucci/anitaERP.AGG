<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultalocalidadModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Localidades</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_localidad" class="col-form-label">Buscar:</label>
              <input type="text" name="consultalocalidad" id="consultalocalidad" autofocus>
              <input type="hidden" name="consultalocalidad_id" id="consultalocalidad_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código Postal</th>
              <th>Código Anita</th>
              <th>Id Provincia</th>
              <th>Provincia</th>
              <th>Código SENASA</th>
          </thead>
          <tbody id="datoslocalidad"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultalocalidadModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultalocalidadModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
