@once('anita-modal-consulta-sifab-maestro')
<div class="modal fade" id="consultasifabmaestroModal" role="dialog" aria-labelledby="consultasifabmaestroModalLabel" aria-hidden="true"
     data-recurso="">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultasifabmaestroModalLabel">Maestros SIFAB</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultasifabmaestro" class="col-form-label">Buscar:</label>
          <input type="text" name="consultasifabmaestro" id="consultasifabmaestro" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover" id="tabla-data-sifab-maestro">
          <thead>
              <th>ID</th>
              <th>C&oacute;d. interno</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datossifabmaestro"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultasifabmaestroModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultasifabmaestroModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
