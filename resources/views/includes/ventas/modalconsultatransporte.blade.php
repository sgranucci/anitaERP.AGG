<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultatransporteModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Repartos</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_transporte" class="col-form-label">Buscar:</label>
              <input type="text" name="consultatransporte" id="consultatransporte" autofocus>
              <input type="hidden" name="consultatransporte_id" id="consultatransporte_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Condición de Iva</th>
              <th>Domicilio</th>
              <th>Provincia</th>
              <th>Localidad</th>
              <th></th>
          </thead>
          <tbody id="datostransporte"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultatransporteModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatransporteModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
