<div id="pedido-procesando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 28rem; min-width: 18rem; width: 92vw;">
        <div id="pedido-procesando-spinner">
            <i class="fa fa-spinner fa-spin fa-2x text-warning mb-2" aria-hidden="true"></i>
            <div><strong id="pedido-procesando-titulo">Procesando…</strong></div>
            <div class="small text-muted mt-1" id="pedido-procesando-subtitulo">Por favor espere. No cierre ni recargue la página.</div>
        </div>
        <div id="pedido-procesando-resultado" class="d-none text-left">
            <div class="text-center mb-2">
                <i id="pedido-procesando-resultado-icono" class="fa fa-2x mb-2" aria-hidden="true"></i>
                <div><strong id="pedido-procesando-resultado-titulo"></strong></div>
                <div class="small text-muted mt-1" id="pedido-procesando-resultado-subtitulo"></div>
            </div>
            <ul id="pedido-procesando-resultado-facturas" class="list-unstyled mb-2 small"></ul>
            <ul id="pedido-procesando-resultado-errores" class="list-unstyled mb-0 small text-danger"></ul>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-primary btn-sm" id="pedido-procesando-resultado-cerrar">Continuar</button>
            </div>
        </div>
    </div>
</div>
