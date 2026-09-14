{{-- CSRF vía layout / meta de la página --}}
<div class="modal fade" id="consultacolorModal" tabindex="-1" role="dialog" aria-labelledby="consultacolorLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacolorLabel">Colores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultacolor" class="col-form-label">Buscar:</label>
          <input type="text" name="consultacolor" id="consultacolor" class="form-control" autocomplete="off" autofocus>
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead>
            <tr>
              <th>ID</th>
              <th>Código</th>
              <th>Nombre</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="datoscolor"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacolorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
