{{-- Modal de consulta de clientes/medidores (proveedor_servicio) de los proveedores elegidos en «Comprobantes en PDF». --}}
<div class="modal fade" id="consultaservicioPdfLoteModal" role="dialog" aria-labelledby="consultaservicioPdfLoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultaservicioPdfLoteModalLabel">Clientes / medidores</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consultaservicio-pdf-lote" class="col-form-label col-auto pr-2 mb-0">Buscar:</label>
          <div class="col">
            <input type="text" id="consultaservicio-pdf-lote" class="form-control form-control-sm"
              autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off"
              placeholder="Nº de cliente / medidor o detalle…"
              role="searchbox" data-lpignore="true" data-1p-ignore="true">
          </div>
        </div>
        <div id="consultaservicio-pdf-lote-aviso" class="small text-muted mb-2"></div>

        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-consulta-servicio-pdf-lote">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>Proveedor</th>
                <th>Cliente / medidor</th>
                <th>Detalle</th>
                <th style="width: 150px;">Acciones</th>
              </tr>
            </thead>
            <tbody id="datosservicio-pdf-lote">
              <tr><td colspan="4" class="text-muted">Escriba para buscar…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
