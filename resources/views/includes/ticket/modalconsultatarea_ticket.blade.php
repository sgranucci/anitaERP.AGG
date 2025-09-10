<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultatarea_ticketModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Tareas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_tarea_ticket" class="col-form-label">Buscar:</label>
              <input type="text" name="consultatarea_ticket" id="consultatarea_ticket" autofocus>
              <input type="hidden" name="consultatarea_ticket_id" id="consultatarea_ticket_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Area de destino</th>
              <th>ID Area destino</th>
          </thead>
          <tbody id="datostarea_ticket"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultatarea_ticketModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatarea_ticketModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
