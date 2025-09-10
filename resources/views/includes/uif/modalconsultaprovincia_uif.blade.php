<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaprovincia_uifModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Provincias UIF</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_provincia_uif" class="col-form-label">Buscar:</label>
              <input type="text" name="consultaprovincia_uif" id="consultaprovincia_uif" autofocus>
              <input type="hidden" name="consultaprovincia_uif_id" id="consultaprovincia_uif_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Riesgo</th>
              <th>Puntaje</th>
          </thead>
          <tbody id="datosprovincia_uif"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaprovincia_uifModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaprovincia_uifModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
