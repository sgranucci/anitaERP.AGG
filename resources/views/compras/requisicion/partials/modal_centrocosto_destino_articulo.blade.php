{{-- Modal: el CC destino actual no está habilitado en la cuenta del artículo. --}}
<div class="modal fade" id="modalReqCcDestinoArticulo" tabindex="-1" role="dialog"
     aria-labelledby="modalReqCcDestinoArticuloTitulo" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalReqCcDestinoArticuloTitulo">
                    Centro de costo destino
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="modalReqCcDestinoArticuloMensaje"></p>
                <p class="small text-muted mb-3" id="modalReqCcDestinoArticuloArticulo"></p>
                <div class="form-group mb-0" id="modalReqCcDestinoArticuloGrupo">
                    <label for="modalReqCcDestinoArticuloSelect" class="requerido">CC destino habilitado</label>
                    <select id="modalReqCcDestinoArticuloSelect" class="form-control"></select>
                </div>
                <div class="alert alert-danger d-none mb-0 mt-3" id="modalReqCcDestinoArticuloSinOpciones" role="alert">
                    No hay centros de costo habilitados disponibles en el combo de la l&iacute;nea.
                    Revise la cuenta contable del art&iacute;culo o la lista de centros de costo.
                </div>
                <div class="alert alert-danger d-none mb-0 mt-3" id="modalReqCcDestinoArticuloError" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="modalReqCcDestinoArticuloCancelar">
                    Cancelar art&iacute;culo
                </button>
                <button type="button" class="btn btn-primary" id="modalReqCcDestinoArticuloConfirmar">
                    Usar este CC destino
                </button>
            </div>
        </div>
    </div>
</div>
