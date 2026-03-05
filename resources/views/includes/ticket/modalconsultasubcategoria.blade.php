<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultasubcategoria_ticketModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Subcategorías</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_subcategoria_ticket" class="col-form-label">Buscar:</label>
              <input type="text" name="consultasubcategoria_ticket" id="consultasubcategoria_ticket" autofocus>
              <input type="hidden" name="consultasubcategoria_ticket_id" id="consultasubcategoria_ticket_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Categoría</th>
              <th>ID Categoría</th>
              <th>Area de destino</th>
              <th>ID Area destino</th>
          </thead>
          <tbody id="datossubcategoria_ticket"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultasubcategoria_ticketModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultasubcategoria_ticketModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
