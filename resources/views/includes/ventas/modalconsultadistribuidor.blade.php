<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultadistribuidorModal" role="dialog" aria-labelledby="consultadistribuidorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultadistribuidorModalLabel">Distribuidores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
          <div class="form-group row">
            <label for="consultadistribuidor" class="col-form-label">Buscar:</label>
            <input type="text" name="consultadistribuidor" id="consultadistribuidor" autofocus>
          </div>
        </form>

        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>C&oacute;digo Anita</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosdistribuidor"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultadistribuidorModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultadistribuidorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
