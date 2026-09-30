<div class="modal fade" id="modalOpEnviarProveedor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
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
                        Los archivos que sume abajo también van en el correo y quedan asociados a la OP.
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
                    <div class="form-group">
                        <label for="op_envio_proveedor_mensaje">Mensaje adicional <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control" id="op_envio_proveedor_mensaje" rows="3" maxlength="4000"
                            placeholder="Texto que se incluirá en el cuerpo del mail"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label>Archivos adjuntos <span class="text-muted">(opcional)</span></label>
                        <div id="op-envio-archivos-actuales" class="alert alert-secondary small d-none py-2" role="status"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-2">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Archivo</th>
                                        <th style="width: 70px;" class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="op-envio-tbody-archivos">
                                    <tr class="op-envio-archivo-fila">
                                        <td>
                                            <input type="file" class="form-control form-control-sm op-envio-archivo">
                                        </td>
                                        <td class="text-center align-middle">
                                            <button type="button" class="btn-accion-tabla op-envio-quitar-archivo" title="Quitar archivo">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <template id="op-envio-template-archivo">
                            <tr class="op-envio-archivo-fila">
                                <td>
                                    <input type="file" class="form-control form-control-sm op-envio-archivo">
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn-accion-tabla op-envio-quitar-archivo" title="Quitar archivo">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="op-envio-agrega-archivo">
                            <i class="fa fa-plus"></i> Agrega archivo
                        </button>
                        <small class="form-text text-muted">
                            Hasta 10 archivos, 10 MB cada uno. Quedan guardados en la orden de pago.
                        </small>
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
