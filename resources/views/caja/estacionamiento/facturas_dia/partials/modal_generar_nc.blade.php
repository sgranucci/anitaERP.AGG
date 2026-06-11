<div class="modal fade" id="modal-fd-generar-nc" tabindex="-1" role="dialog" aria-labelledby="modal-fd-generar-nc-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-fd-generar-nc-title">Generar nota de crédito</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar" id="fd-nc-cerrar-x">&times;</button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-2 small">
                    Se va a <strong>revertir</strong> el comprobante <strong id="fd-nc-compro">—</strong> emitiendo una nota de crédito por el mismo importe.
                </p>
                <ul class="small mb-3 pl-3">
                    <li>La factura original no se borra: queda compensada fiscalmente por la NC.</li>
                    <li>La NC se registra en el turno de esta terminal.</li>
                </ul>
                <div class="form-group mb-0">
                    <label for="fd-nc-leyenda" class="small mb-1">Leyenda (motivo de la reversión)</label>
                    <textarea id="fd-nc-leyenda" class="form-control form-control-sm" rows="3"
                              maxlength="255" placeholder="Opcional. Ej.: Error en cobranza; devolución; ticket duplicado…"></textarea>
                    <small class="text-muted">Se guarda en la leyenda del comprobante de la nota de crédito.</small>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" id="fd-nc-cancelar">Cancelar</button>
                <button type="button" class="btn btn-sm btn-warning" id="fd-nc-confirmar">
                    <i class="fas fa-undo" id="fd-nc-confirmar-icono" aria-hidden="true"></i>
                    <span id="fd-nc-confirmar-text">Generar nota de crédito</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="fd-nc-procesando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; align-items: center; justify-content: center; padding: 1rem;">
    <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 18rem;">
        <i class="fa fa-spinner fa-spin fa-2x text-warning mb-2" aria-hidden="true"></i>
        <div><strong>Generando nota de crédito…</strong></div>
        <div id="fd-nc-procesando-detalle" class="small text-muted mt-1">Por favor espere. No cierre ni recargue la página.</div>
    </div>
</div>
