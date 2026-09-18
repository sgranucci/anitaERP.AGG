@once('anita-modal-consulta-facturas-articulo')
<div class="modal fade" id="consultafacturasarticuloModal" role="dialog" aria-labelledby="consultafacturasarticuloModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header" style="background-color:#85C1E9;color:#17202A;">
        <h5 class="modal-title" id="consultafacturasarticuloModalLabel">Facturas del cliente</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group row align-items-center mb-2">
          <label for="consulta-factura-articulo" class="col-form-label mb-0 mr-2">Buscar:</label>
          <input type="text" id="consulta-factura-articulo" class="form-control col-lg-5" autocomplete="off" placeholder="C&oacute;digo, PV, n&uacute;mero…">
        </div>
        <p id="consulta-factura-articulo-contexto" class="small text-muted mb-2"></p>
        <table class="table table-sm table-striped table-bordered table-hover" id="tabla-data-factura-articulo">
          <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
              <th>Comprobante</th>
              <th>Fecha</th>
              <th class="text-right">Cant. art.</th>
              <th class="text-right">Pendiente</th>
              <th class="text-right">Total</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="datos-factura-articulo"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endonce
