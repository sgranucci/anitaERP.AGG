<div class="modal fade" id="modalDescuentoComprobante" tabindex="-1" role="dialog" aria-labelledby="modalDescuentoComprobanteLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDescuentoComprobanteLabel">Descuento — nota de crédito</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">
                    Comprobante: <strong id="descuento-modal-codigo"></strong>
                    — Saldo aplicable: <strong id="descuento-modal-saldo"></strong>
                </p>
                <div class="form-group">
                    <label for="descuento_modal_tipo">Tipo de descuento</label>
                    <select id="descuento_modal_tipo" class="form-control">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="importe">Importe</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="descuento_modal_valor">Valor</label>
                    <input type="number" step="0.01" min="0" id="descuento_modal_valor" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="descuento_modal_leyenda">Leyenda NC (opcional)</label>
                    <input type="text" maxlength="200" id="descuento_modal_leyenda" class="form-control" placeholder="Descuento por pronto pago" />
                </div>
                <p class="mb-0">
                    Importe descuento: <strong id="descuento-modal-preview">0.00</strong>
                </p>
                <p class="small text-muted mt-2 mb-0">
                    La nota de crédito se emitirá en ARCA al confirmar la cobranza.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="descuento_modal_quitar" style="display:none;">Quitar descuento</button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm" id="descuento_modal_aplicar">Aplicar descuento</button>
            </div>
        </div>
    </div>
</div>
