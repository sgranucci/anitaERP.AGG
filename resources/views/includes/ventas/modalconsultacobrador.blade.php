<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultacobradorModal" role="dialog" aria-labelledby="consultacobradorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacobradorModalLabel">Cobradores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post">
          <div class="form-group row">
            <label for="consultacobrador" class="col-form-label">Buscar:</label>
            <input type="text" name="consultacobrador" id="consultacobrador" autofocus>
          </div>
        </form>

        <table class="table table-striped table-bordered table-hover" id="tabla-data">
          <thead>
              <th>ID</th>
              <th>Nombre</th>
              <th>C&oacute;digo Anita</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscobrador"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacobradorModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacobradorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
