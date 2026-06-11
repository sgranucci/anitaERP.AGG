<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="modal fade" id="consultaclientevipModal" role="dialog" aria-labelledby="consultaclientevipModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaclientevipModalLabel">Clientes VIP — canjes marketing</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultaclientevip" class="col-form-label">Buscar:</label>
          <input type="text" name="consultaclientevip" id="consultaclientevip" class="form-control col-sm-8" placeholder="Apellido, nombre, documento o código Anita" autofocus>
        </div>
        <table class="table table-striped table-bordered table-hover" id="tabla-data-cliente-vip">
          <thead>
              <th>ID</th>
              <th>Cód. Anita</th>
              <th>Documento</th>
              <th>Nombre</th>
              <th>Nickname</th>
              <th>Localidad</th>
              <th>Empresa</th>
              <th></th>
          </thead>
          <tbody id="datosclientevip"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaclientevipModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaclientevipModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
