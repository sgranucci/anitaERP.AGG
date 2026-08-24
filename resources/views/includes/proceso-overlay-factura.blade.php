<div id="factura-procesando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 2050; display: flex; align-items: center; justify-content: center; padding: 1.25rem; pointer-events: all;">
    <div class="alert alert-warning shadow-lg mb-0 text-center px-4 py-3 border border-warning"
         style="max-width: 28rem; min-width: 20rem; width: 92vw; font-size: 1rem;">
        <div id="factura-procesando-spinner">
            <i class="fa fa-spinner fa-spin fa-2x text-danger mb-2 d-block" aria-hidden="true"></i>
            <strong id="factura-procesando-titulo">Generando comprobante…</strong>
            <div class="small mt-2" id="factura-procesando-subtitulo">Por favor espere. No cierre ni recargue la página.</div>
        </div>
        <div id="factura-procesando-resultado" class="d-none text-left">
            <div class="text-center mb-2">
                <i id="factura-procesando-resultado-icono" class="fa fa-2x mb-2" aria-hidden="true"></i>
                <div><strong id="factura-procesando-resultado-titulo"></strong></div>
                <div class="small mt-1" id="factura-procesando-resultado-subtitulo"></div>
            </div>
            <ul id="factura-procesando-resultado-facturas" class="list-unstyled mb-2 small"></ul>
            <ul id="factura-procesando-resultado-errores" class="list-unstyled mb-0 small text-danger"></ul>
            <div class="text-center mt-3">
                <button type="button" class="btn btn-primary btn-sm" id="factura-procesando-resultado-cerrar">Continuar</button>
            </div>
        </div>
    </div>
</div>
