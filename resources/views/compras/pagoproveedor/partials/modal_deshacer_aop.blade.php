<div class="modal fade" id="modalOpDeshacerAop" tabindex="-1" role="dialog" aria-labelledby="modalOpDeshacerAopLabel" aria-hidden="true"
     data-url-plan-template="{{ url('compras/pagoproveedor/__ID__/deshacer-aop/plan') }}"
     data-url-post-template="{{ url('compras/pagoproveedor/__ID__/deshacer-aop') }}">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalOpDeshacerAopLabel">Deshacer AOP</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="op-deshacer-aop-cargando" class="text-muted d-none">Consultando el plan…</div>
        <div id="op-deshacer-aop-error" class="alert alert-danger d-none"></div>
        <div id="op-deshacer-aop-contenido" class="d-none">
          <p class="mb-1">
            <strong id="op-deshacer-aop-aop"></strong>
            <span class="text-muted" id="op-deshacer-aop-aop-detalle"></span>
          </p>
          <p class="mb-3">
            OPP original: <strong id="op-deshacer-aop-origen"></strong>
            <span class="text-muted" id="op-deshacer-aop-origen-detalle"></span>
          </p>
          <div id="op-deshacer-aop-bloqueos" class="alert alert-danger d-none">
            <strong>No se puede deshacer:</strong>
            <ul class="mb-0" id="op-deshacer-aop-bloqueos-lista"></ul>
          </div>
          <div id="op-deshacer-aop-plan">
            <p class="mb-1"><strong>Se va a:</strong></p>
            <ul id="op-deshacer-aop-lista"></ul>
            <p class="text-muted small mb-0">
              No se toca: el asiento original de la OPP, la cuenta corriente de la factura ni la OPP en Anita.
            </p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
        <form id="form-op-deshacer-aop" method="POST" action="#" class="d-inline">
          @csrf
          <button type="submit" id="op-deshacer-aop-confirmar" class="btn btn-danger btn-sm d-none">
            <i class="fa fa-reply-all"></i> Deshacer AOP
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
