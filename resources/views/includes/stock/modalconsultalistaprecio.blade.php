<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultalistaprecioModal" role="dialog" aria-labelledby="consultalistaprecioModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultalistaprecioModalLabel">Listas de precios</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
          <div class="form-group row">
            <label for="consultalistaprecio" class="col-form-label">Buscar:</label>
            <input type="text" name="consultalistaprecio" id="consultalistaprecio" autofocus>
          </div>
        </form>

        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>C&oacute;digo Anita</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoslistaprecio"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultalistaprecioModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultalistaprecioModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
