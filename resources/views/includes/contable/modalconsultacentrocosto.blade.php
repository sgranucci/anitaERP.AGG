@once('anita-modal-consulta-centrocosto')
<div class="modal fade" id="consultacentrocostoModal" role="dialog" aria-labelledby="consultacentrocostoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacentrocostoModalLabel">Centros de costo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultacentrocosto" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacentrocosto" id="consultacentrocosto" class="form-control form-control-sm"
            placeholder="C&oacute;digo, nombre o abreviatura" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover table-sm" id="tabla-data-centrocosto">
          <thead>
              <th>ID</th>
              <th>C&oacute;digo</th>
              <th>Nombre</th>
              <th>Abrev.</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datoscentrocosto"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultacentrocostoModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
