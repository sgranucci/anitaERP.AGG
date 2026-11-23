{{-- Modal consulta prenda (sueldos / indumentaria). Sin form anidado. --}}
<div class="modal fade" id="consultaprendaModal" tabindex="-1" role="dialog" aria-labelledby="consultaprendaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaprendaLabel">Prendas</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row">
          <label for="consultaprenda" class="col-form-label">Buscar:</label>
          <input type="text" name="consultaprenda" id="consultaprenda" class="form-control" autocomplete="off" autofocus>
        </div>
        <div class="table-responsive" style="max-height: 60vh;">
          <table class="table table-striped table-bordered table-hover table-sm mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Descripción</th>
                <th style="min-width:10rem;"></th>
              </tr>
            </thead>
            <tbody id="datosprenda"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaprendaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
