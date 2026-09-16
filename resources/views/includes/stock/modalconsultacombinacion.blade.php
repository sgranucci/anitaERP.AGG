{{-- Combinaciones del artículo (lista en JS; búsqueda + Elegir / Seleccionar todas) --}}
<div class="modal fade" id="consultacombinacionModal" tabindex="-1" role="dialog" aria-labelledby="consultacombinacionLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultacombinacionLabel">Combinaciones</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consultacombinacion" class="col-form-label col-auto pr-2 mb-0">Buscar:</label>
          <div class="col">
            <input type="text" name="consultacombinacion" id="consultacombinacion" class="form-control" autocomplete="off" autofocus>
          </div>
          <div class="col-auto">
            <button type="button" class="btn btn-outline-primary btn-sm" id="seleccionartodascombinacionModal" title="Consultar pedidos de todas las combinaciones del artículo">
              <i class="fa fa-check-double"></i> Seleccionar todas
            </button>
          </div>
        </div>
        <table class="table table-striped table-bordered table-hover table-sm">
          <thead style="background:#85C1E9;color:#17202A;">
            <tr>
              <th>ID</th>
              <th>Código</th>
              <th>Nombre</th>
              <th class="width80">Acciones</th>
            </tr>
          </thead>
          <tbody id="datoscombinacion"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultacombinacionModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
