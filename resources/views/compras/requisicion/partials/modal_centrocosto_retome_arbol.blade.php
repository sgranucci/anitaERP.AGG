<div class="modal fade" id="modalRequisicionCentrocostoRetomeArbol" tabindex="-1" role="dialog" aria-labelledby="modalRequisicionCentrocostoRetomeArbolTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRequisicionCentrocostoRetomeArbolTitulo">Seleccionar centro de costo de destino</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3" id="requisicionCentrocostoRetomeArbolTexto">
                    La requisici&oacute;n tiene renglones con distintos centros de costo de destino. Elija con cu&aacute;l enviar al &aacute;rbol de aprobaci&oacute;n.
                </p>
                <div id="requisicionCentrocostoRetomeArbolLista"></div>
                <div class="alert alert-danger d-none mt-2 mb-0" id="requisicionCentrocostoRetomeArbolError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="requisicionCentrocostoRetomeArbolConfirmar">
                    <i class="fa fa-sitemap"></i> Continuar
                </button>
            </div>
        </div>
    </div>
</div>
