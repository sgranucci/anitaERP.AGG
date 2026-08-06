<div class="modal fade" id="modalRequisicionConfirmarEnvioArbol" tabindex="-1" role="dialog" aria-labelledby="modalRequisicionConfirmarEnvioArbolTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRequisicionConfirmarEnvioArbolTitulo">Confirmar requisici&oacute;n</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-3" id="requisicionConfirmarEnvioArbolTexto">
                    La requisici&oacute;n se enviar&aacute; al &aacute;rbol de aprobaci&oacute;n y se sincronizar&aacute; con Anita.
                </p>
                <div class="form-group mb-0">
                    <label for="requisicionConfirmarEnvioArbolObservacion">Comentario al &aacute;rbol <span class="text-muted font-weight-normal">(opcional)</span></label>
                    <textarea id="requisicionConfirmarEnvioArbolObservacion" class="form-control" rows="3" maxlength="255"
                              placeholder="Motivo, urgencia u observaciones para el firmante…"></textarea>
                    <small class="form-text text-muted">Se muestra al firmante en el mail, el portal y el seguimiento del &aacute;rbol.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="requisicionConfirmarEnvioArbolConfirmar">
                    <i class="fa fa-check"></i> Confirmar y enviar
                </button>
            </div>
        </div>
    </div>
</div>
