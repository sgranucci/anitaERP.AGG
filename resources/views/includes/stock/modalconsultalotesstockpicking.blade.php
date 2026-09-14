@once('anita-modal-consulta-lotes-stock-picking')
<div class="modal fade" id="consultalotesstockpickingModal" role="dialog" aria-labelledby="consultalotesstockpickingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultalotesstockpickingModalLabel">M&oacute;dulos / lotes de stock pendientes</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row mb-2">
          <label for="consultalotesstockpicking" class="col-form-label col-auto pr-2">Buscar:</label>
          <div class="col">
            <input type="text" name="consultalotesstockpicking" id="consultalotesstockpicking" class="form-control form-control-sm" autocomplete="off" placeholder="Lote / OT / m&oacute;dulo">
          </div>
          <div class="col-auto">
            <div class="custom-control custom-checkbox mt-1">
              <input type="checkbox" class="custom-control-input" id="consultalotesstockpicking_solo_modulo" checked>
              <label class="custom-control-label" for="consultalotesstockpicking_solo_modulo">Solo m&oacute;dulo de la l&iacute;nea</label>
            </div>
          </div>
        </div>
        <p class="text-muted small mb-2" id="consultalotesstockpicking-contexto"></p>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-data-lotes-stock-picking">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>Lote / OT</th>
                <th>M&oacute;dulo</th>
                <th>Dep&oacute;sito</th>
                <th class="text-right">Saldo</th>
                <th style="width:1%;">Acciones</th>
              </tr>
            </thead>
            <tbody id="datoslotesstockpicking"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultalotesstockpickingModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
