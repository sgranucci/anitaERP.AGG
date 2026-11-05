@once('anita-modal-consulta-moneda')
<div class="modal fade" id="consultamonedaModal" role="dialog" aria-labelledby="consultamonedaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultamonedaModalLabel">Monedas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultamoneda" class="col-form-label">Buscar:</label>
          <input type="text" name="consultamoneda" id="consultamoneda" autocomplete="off">
        </div>

        <table class="table table-striped table-bordered table-hover" id="tabla-data-moneda">
          <thead style="background-color:#85C1E9;color:#17202A;">
              <th class="d-none">ID</th>
              <th>C&oacute;digo</th>
              <th>Abreviatura</th>
              <th>Nombre</th>
              <th>Acciones</th>
          </thead>
          <tbody id="datosmoneda"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultamonedaModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultamonedaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
@endonce
