<div class="modal fade" id="modalOpDevolverRetenciones" tabindex="-1" role="dialog" aria-labelledby="modalOpDevolverRetencionesLabel" aria-hidden="true"
     data-url-datos-template="{{ url('compras/pagoproveedor/__ID__/devolver-retenciones/datos') }}"
     data-url-post-template="{{ url('compras/pagoproveedor/__ID__/devolver-retenciones') }}">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalOpDevolverRetencionesLabel">Devolver retenciones</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="op-devret-cargando" class="text-muted d-none">Cargando retenciones…</div>
        <div id="op-devret-error" class="alert alert-danger d-none"></div>
        <div id="op-devret-form-wrap" class="d-none">
          <p class="mb-2">
            <strong id="op-devret-comprobante"></strong>
            <span class="text-muted" id="op-devret-proveedor"></span>
          </p>
          <div class="alert alert-warning py-2">
            La OP original permanece confirmada. Esto no es un AOP total: solo se devuelven las retenciones seleccionadas.
          </div>
          <div class="form-group row">
            <label class="col-form-label col-lg-3" for="op_devret_fecha">Fecha contable</label>
            <input type="date" id="op_devret_fecha" class="form-control col-lg-4" value="{{ date('Y-m-d') }}">
          </div>
          <div class="table-responsive mb-3">
            <table class="table table-sm table-bordered mb-0">
              <thead>
                <tr>
                  <th style="width:2rem;"></th>
                  <th>Tipo</th>
                  <th class="text-right">Original</th>
                  <th class="text-right">Ya devuelto</th>
                  <th class="text-right">Disponible</th>
                  <th class="text-right" style="min-width:8rem;">A devolver</th>
                </tr>
              </thead>
              <tbody id="op-devret-retenciones-body"></tbody>
            </table>
          </div>
          <div class="form-group row">
            <label class="col-form-label col-lg-3">Medio de pago</label>
            <div class="col-lg-9 pt-2">
              <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="op_devret_medio_trf" name="op_devret_medio" class="custom-control-input" value="transferencia" checked>
                <label class="custom-control-label" for="op_devret_medio_trf">Transferencia / banco</label>
              </div>
              <div class="custom-control custom-radio custom-control-inline">
                <input type="radio" id="op_devret_medio_chp" name="op_devret_medio" class="custom-control-input" value="chp">
                <label class="custom-control-label" for="op_devret_medio_chp">Cheque propio (CHP)</label>
              </div>
            </div>
          </div>
          <div class="form-group row" id="op-devret-wrap-cuenta">
            <label class="col-form-label col-lg-3" for="op_devret_cuentacaja">Cuenta banco</label>
            <select id="op_devret_cuentacaja" class="form-control col-lg-7"></select>
          </div>
          <div id="op-devret-wrap-chp" class="d-none">
            <div class="form-group row">
              <label class="col-form-label col-lg-3" for="op_devret_chequera">Chequera</label>
              <select id="op_devret_chequera" class="form-control col-lg-7"></select>
            </div>
            <div class="form-group row">
              <label class="col-form-label col-lg-3" for="op_devret_numerocheque">Nº cheque</label>
              <input type="text" id="op_devret_numerocheque" class="form-control col-lg-4" placeholder="(automático si vacío)">
            </div>
            <div class="form-group row">
              <label class="col-form-label col-lg-3" for="op_devret_fechapago">Fecha pago cheque</label>
              <input type="date" id="op_devret_fechapago" class="form-control col-lg-4" value="{{ date('Y-m-d') }}">
            </div>
          </div>
          <div class="form-group row">
            <label class="col-form-label col-lg-3" for="op_devret_obs">Observación</label>
            <input type="text" id="op_devret_obs" class="form-control col-lg-7" maxlength="120" placeholder="Devolución de retenciones">
          </div>
          <p class="mb-0"><strong>Total a devolver:</strong> <span id="op-devret-total">0,00</span></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary d-none" id="op_devret_confirmar">Confirmar devolución</button>
      </div>
    </div>
  </div>
</div>
