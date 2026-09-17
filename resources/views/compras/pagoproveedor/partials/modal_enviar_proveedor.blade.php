<div class="modal fade" id="modalOpEnviarProveedor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enviar orden de pago por email</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="op-envio-proveedor-cargando" class="text-muted small d-none">
                    <i class="fa fa-spinner fa-spin"></i> Cargando datos…
                </div>
                <div id="op-envio-proveedor-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="op-envio-proveedor-form-wrap" class="d-none">
                    <p class="small text-muted mb-2">
                        Se adjuntará el PDF de la orden de pago al correo indicado.
                    </p>
                    <div id="op-envio-proveedor-aviso" class="alert alert-info small d-none" role="alert"></div>
                    <div id="op-envio-proveedor-advertencia" class="alert alert-warning small d-none" role="alert"></div>
                    <div class="form-group">
                        <label for="op_envio_proveedor_email">Email destino <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="op_envio_proveedor_email" maxlength="500"
                            placeholder="destinatario@empresa.com, otro@empresa.com"
                            autocomplete="email">
                        <small class="form-text text-muted">
                            Puede indicar varios separados por coma o punto y coma. Se validará cada dirección.
                        </small>
                        <div id="op-envio-proveedor-email-error" class="invalid-feedback d-block d-none"></div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="op_envio_proveedor_mensaje">Mensaje adicional <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="op_envio_proveedor_mensaje" rows="3" maxlength="4000"
                            placeholder="Texto que se incluirá en el cuerpo del mail"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success d-none" id="op_envio_proveedor_confirmar">
                    <i class="fa fa-paper-plane"></i> Enviar ahora
                </button>
            </div>
        </div>
    </div>
</div>
