<div class="modal fade" id="arca-apoc-validacion-modal" tabindex="-1" role="dialog"
     aria-labelledby="arca-apoc-validacion-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="arca-apoc-validacion-titulo">
                    <i class="fa fa-exclamation-triangle"></i> Facturas ap&oacute;crifas — ARCA (WSAPOC)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="arca-apoc-validacion-mensaje" class="mb-2"></p>
                <ul id="arca-apoc-validacion-detalles" class="mb-0 pl-3 small text-muted" style="display: none;"></ul>
                <p class="small text-muted mb-0 mt-3">
                    La consulta se realiz&oacute; en segundo plano. Si el contribuyente figura en la base APOC,
                    qued&oacute; suspendido autom&aacute;ticamente hasta regularizar la situaci&oacute;n ante AFIP/ARCA.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
