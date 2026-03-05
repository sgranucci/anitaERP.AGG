<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultareservaModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Reservas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_reserva" class="col-form-label">Buscar:</label>
              <input type="text" name="consultareserva" id="consultareserva" autofocus>
              <input type="hidden" name="consultareserva_id" id="consultareserva_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Fecha IN</th>
              <th>Fecha OUT</th>
              <th>Pax</th>
              <th>Opcion</th>
          </thead>
          <tbody id="datosreserva"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultareservaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultareservaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
