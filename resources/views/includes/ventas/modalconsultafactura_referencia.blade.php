@once('anita-modal-consulta-factura-referencia')
<div class="modal fade" id="consultafacturareferenciaModal" role="dialog" aria-labelledby="consultafacturareferenciaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
        <h5 class="modal-title" id="consultafacturareferenciaModalLabel">Comprobantes del cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consulta-factura-referencia" class="col-form-label mb-0 mr-2">Buscar:</label>
          <input type="text" id="consulta-factura-referencia" class="form-control col-lg-5" autocomplete="off" placeholder="C&oacute;digo, PV, n&uacute;mero…">
          <div class="custom-control custom-checkbox ml-3">
            <input type="checkbox" class="custom-control-input" id="consulta-factura-referencia-solo-fce">
            <label class="custom-control-label" for="consulta-factura-referencia-solo-fce">Solo FCE</label>
          </div>
        </div>
        <p id="consulta-factura-referencia-cliente" class="small text-muted mb-2"></p>
        <table class="table table-sm table-striped table-bordered table-hover" id="tabla-data-factura-referencia">
          <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
              <th>Comprobante</th>
              <th>Fecha</th>
              <th class="text-right">Total</th>
              <th>Origen</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="datos-factura-referencia"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endonce
