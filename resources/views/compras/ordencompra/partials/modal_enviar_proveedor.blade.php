<div class="modal fade" id="modalOcEnviarProveedor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Enviar orden de compra al proveedor</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="oc-envio-proveedor-cargando" class="text-muted small d-none">
                    <i class="fa fa-spinner fa-spin"></i> Cargando datos…
                </div>
                <div id="oc-envio-proveedor-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="oc-envio-proveedor-form-wrap" class="d-none">
                    <p id="oc-envio-proveedor-cola-info" class="small text-info mb-2 d-none"></p>
                    <p class="small text-muted mb-2">
                        Se adjuntará el PDF de la orden de compra al correo indicado.
                    </p>
                    <div id="oc-envio-proveedor-advertencia" class="alert alert-warning small d-none" role="alert"></div>
                    <div class="form-group">
                        <label for="oc_envio_proveedor_email">Email destino</label>
                        <input type="text" class="form-control" id="oc_envio_proveedor_email" maxlength="500"
                            placeholder="Email OC del proveedor">
                        <small class="form-text text-muted">Puede indicar varios separados por coma o punto y coma.</small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="oc_envio_proveedor_mensaje">Mensaje adicional <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="oc_envio_proveedor_mensaje" rows="3" maxlength="4000"
                            placeholder="Texto que se incluirá en el cuerpo del mail"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary d-none" id="oc_envio_proveedor_omitir_restantes">Omitir restantes</button>
                <button type="button" class="btn btn-secondary" id="oc_envio_proveedor_cancelar" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-warning d-none" id="oc_envio_proveedor_omitir">Omitir esta OC</button>
                <button type="button" class="btn btn-success d-none" id="oc_envio_proveedor_confirmar">
                    <i class="fa fa-paper-plane"></i> Enviar ahora
                </button>
            </div>
        </div>
    </div>
</div>
