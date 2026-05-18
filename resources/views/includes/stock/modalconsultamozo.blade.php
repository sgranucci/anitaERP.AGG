<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultamozoModal" role="dialog" aria-labelledby="consultamozoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultamozoModalLabel">Mozos gastronomía</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
          <div class="form-group row">
            <label for="consultamozo" class="col-form-label">Buscar:</label>
            <input type="text" name="consultamozo" id="consultamozo" autofocus>
          </div>
        </form>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-mozo">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código</th>
              <th></th>
          </thead>
          <tbody id="datosmozo"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultamozoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultamozoModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
