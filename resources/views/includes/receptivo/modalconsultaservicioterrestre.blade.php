<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaservicioterrestreModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Servicios Terrestres</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
			      <div class="form-group row">
   				    <label for="consulta_servicioterrestre" class="col-form-label">Buscar:</label>
              <input type="text" name="consultaservicioterrestre" id="consultaservicioterrestre" autofocus>
              <input type="hidden" name="consultaservicioterrestre_id" id="consultaservicioterrestre_id">
			      </div>
        </form>
        
        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Descripción</th>
              <th>Abrev.</th>
              <th>Precio</th>
              <th>Moneda</th>
              <th>Código</th>
          </thead>
          <tbody id="datosservicioterrestre"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaservicioterrestreModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaservicioterrestreModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
