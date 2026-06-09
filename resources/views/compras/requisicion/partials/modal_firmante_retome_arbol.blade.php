<div class="modal fade" id="modalRequisicionFirmanteRetomeArbol" tabindex="-1" role="dialog" aria-labelledby="modalRequisicionFirmanteRetomeArbolTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRequisicionFirmanteRetomeArbolTitulo">Seleccionar firmante</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="requisicionFirmanteRetomeArbolTexto">
                    Hay más de un firmante configurado para el siguiente nivel del árbol. Elija a quién enviar la requisición.
                </p>
                <div id="requisicionFirmanteRetomeArbolLista"></div>
                <div class="alert alert-danger d-none mt-2 mb-0" id="requisicionFirmanteRetomeArbolError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="requisicionFirmanteRetomeArbolConfirmar">
                    <i class="fa fa-sitemap"></i> Enviar al árbol
                </button>
            </div>
        </div>
    </div>
</div>
