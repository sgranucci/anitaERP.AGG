<div class="modal fade" id="modalRequisicionFirmanteRetomeArbol" tabindex="-1" role="dialog" aria-labelledby="modalRequisicionFirmanteRetomeArbolTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRequisicionFirmanteRetomeArbolTitulo">Enviar al &aacute;rbol de aprobaci&oacute;n</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="requisicionFirmanteRetomeArbolTexto">
                    Elija el firmante y, si lo desea, agregue un comentario para el circuito de aprobaci&oacute;n.
                </p>
                <div id="requisicionFirmanteRetomeArbolLista"></div>
                <div class="form-group mt-3 mb-0">
                    <label for="requisicionFirmanteRetomeArbolObservacion">Comentario al &aacute;rbol <span class="text-muted font-weight-normal">(opcional)</span></label>
                    <textarea id="requisicionFirmanteRetomeArbolObservacion" class="form-control" rows="3" maxlength="255"
                              placeholder="Motivo, urgencia u observaciones para el firmante…"></textarea>
                    <small class="form-text text-muted">Se muestra al firmante en el mail, el portal y el seguimiento del &aacute;rbol.</small>
                </div>
                <div class="alert alert-danger d-none mt-2 mb-0" id="requisicionFirmanteRetomeArbolError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="requisicionFirmanteRetomeArbolConfirmar">
                    <i class="fa fa-sitemap"></i> Enviar al &aacute;rbol
                </button>
            </div>
        </div>
    </div>
</div>
