<div class="modal fade" id="modalConfirmacionTicketCanje" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar emisión</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-sm-5">Documento</dt>
          <dd class="col-sm-7" id="prev-documento">—</dd>
          <dt class="col-sm-5">Cliente</dt>
          <dd class="col-sm-7" id="prev-cliente">—</dd>
          <dt class="col-sm-5">Tipo</dt>
          <dd class="col-sm-7" id="prev-tipo">—</dd>
          <dt class="col-sm-5">Monto venta</dt>
          <dd class="col-sm-7" id="prev-monto-venta">—</dd>
          <dt class="col-sm-5">Monto ticket (total)</dt>
          <dd class="col-sm-7" id="prev-monto-ticket">—</dd>
          <dt class="col-sm-5">Emisión</dt>
          <dd class="col-sm-7" id="prev-cantidad">—</dd>
        </dl>
        <p class="text-muted small mt-2 mb-0" id="prev-aviso-impresion"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="btn-confirmar-emitir">
          Grabar
          <span id="prev-label-imprimir">e imprimir</span>
        </button>
      </div>
    </div>
  </div>
</div>
