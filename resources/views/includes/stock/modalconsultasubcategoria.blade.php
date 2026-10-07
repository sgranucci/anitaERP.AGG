<div class="modal fade" id="consultasubcategoriaModal" role="dialog" aria-labelledby="consultasubcategoriaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultasubcategoriaModalLabel">Subcategor&iacute;as</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="" method="post" onsubmit="return false;">
          <div class="form-group row">
            <label for="consultasubcategoria" class="col-form-label">Buscar:</label>
            <input type="text" name="consultasubcategoria" id="consultasubcategoria" class="form-control" autocomplete="off">
          </div>
        </form>
        <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
          <table class="table table-striped table-bordered table-hover mb-0">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>C&oacute;digo</th>
                <th>Nombre</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody id="datossubcategoria"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultasubcategoriaModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
