<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultadescuentoModal" role="dialog" aria-labelledby="consultadescuentoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultadescuentoModalLabel">Descuentos gastronomía</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
          <div class="form-group row">
            <label for="consultadescuento" class="col-form-label">Buscar:</label>
            <input type="text" name="consultadescuento" id="consultadescuento" autofocus>
          </div>
        </form>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-descuento">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>Código</th>
              <th>Tipo</th>
              <th>Valor</th>
              <th>Cliente consumo interno</th>
              <th></th>
          </thead>
          <tbody id="datosdescuento"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultadescuentoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultadescuentoModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
