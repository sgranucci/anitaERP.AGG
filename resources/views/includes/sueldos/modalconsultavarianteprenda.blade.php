{{-- Modal variantes (color/talle/SKU) de una prenda. Sin form anidado. --}}
<div class="modal fade" id="consultavarianteprendaModal" tabindex="-1" role="dialog" aria-labelledby="consultavarianteprendaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultavarianteprendaLabel">Color / Talle (SKU)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2" id="consultavarianteprenda-contexto"></p>
        <div class="form-group row">
          <label for="consultavarianteprenda" class="col-form-label">Buscar:</label>
          <input type="text" name="consultavarianteprenda" id="consultavarianteprenda" class="form-control" autocomplete="off" autofocus>
        </div>
        <div class="table-responsive" style="max-height: 60vh;">
          <table class="table table-striped table-bordered table-hover table-sm mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Color</th>
                <th>Talle</th>
                <th>SKU</th>
                <th class="text-right">Saldo</th>
                <th style="min-width:6rem;"></th>
              </tr>
            </thead>
            <tbody id="datosvarianteprenda"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultavarianteprendaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
