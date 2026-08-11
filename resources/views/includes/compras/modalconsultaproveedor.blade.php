{{-- Modal consulta proveedores. Tabla con id propio (no tabla-data) para no chocar con DataTables. --}}
<div class="modal fade" id="consultaproveedorModal" role="dialog" aria-labelledby="consultaproveedorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaproveedorModalLabel">Proveedores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consultaproveedor" class="col-form-label col-auto pr-2 mb-0">Buscar:</label>
          <div class="col">
            <input type="text" name="consultaproveedor" id="consultaproveedor" class="form-control form-control-sm" autofocus
              placeholder="Código, nombre, domicilio…">
          </div>
        </div>
        <div id="consultaproveedor-aviso" class="small text-muted mb-2 d-none"></div>

        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered table-hover" id="tabla-consulta-proveedor">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>ID</th>
                <th>Código</th>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Localidad</th>
                <th>Teléfono</th>
                <th>Acciones</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="datosproveedor">
              <tr><td colspan="8" class="text-muted">Abrí la consulta o escribí para buscar…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultaproveedorModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
        <button type="button" id="aceptaconsultaproveedorModal" class="btn btn-primary">Acepta</button>
      </div>
    </div>
  </div>
</div>
