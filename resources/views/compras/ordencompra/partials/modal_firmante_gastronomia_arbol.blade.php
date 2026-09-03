<div class="modal fade" id="modalOcFirmanteGastronomiaArbol" tabindex="-1" role="dialog" aria-labelledby="modalOcFirmanteGastronomiaArbolTitulo" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalOcFirmanteGastronomiaArbolTitulo">Enviar a Gastronomía</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="ocFirmanteGastronomiaArbolTexto">
                    Hay más de un firmante que puede recibir la orden de compra. Elija a quién enviarla.
                </p>
                <div id="ocFirmanteGastronomiaArbolLista"></div>
                <div class="alert alert-danger d-none mt-2 mb-0" id="ocFirmanteGastronomiaArbolError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="ocFirmanteGastronomiaArbolConfirmar">
                    <i class="fa fa-cutlery"></i> Enviar a este firmante
                </button>
            </div>
        </div>
    </div>
</div>
