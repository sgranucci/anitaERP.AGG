<div class="modal fade" id="consultatipoarticuloModal" role="dialog" aria-labelledby="consultatipoarticuloModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultatipoarticuloModalLabel">Tipos de art&iacute;culo</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultatipoarticulo" class="col-form-label">Buscar:</label>
            <input type="text" name="consultatipoarticulo" id="consultatipoarticulo" class="form-control" autocomplete="off">
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Abrev.</th>
                <th>Nombre</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datostipoarticulo"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultatipoarticuloModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
