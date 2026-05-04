<div class="modal fade" id="consultacapexModal" role="dialog" aria-labelledby="consultacapexLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacapexLabel">Capex / proyectos (último presupuesto)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultacapex" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacapex" id="consultacapex" autocomplete="off">
        </div>
        <table class="table table-striped table-bordered table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Código</th>
              <th>Detalle</th>
              <th>Nombre</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="datoscapex"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacapexModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
