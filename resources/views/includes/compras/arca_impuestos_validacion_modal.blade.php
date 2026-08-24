<div class="modal fade" id="arca-impuestos-validacion-modal" tabindex="-1" role="dialog"
     aria-labelledby="arca-impuestos-validacion-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="arca-impuestos-validacion-titulo">
                    <i class="fa fa-exclamation-triangle"></i> Padrón ARCA — impuestos
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="arca-imp-validacion-mensaje" class="mb-2"></p>
                <ul id="arca-imp-validacion-detalles" class="mb-0 pl-3 small text-muted" style="display: none;"></ul>
                <p id="arca-imp-validacion-nota" class="small text-muted mb-0 mt-3" style="display: none;">
                    La consulta se realizó en segundo plano; puede seguir trabajando en la pantalla.
                    En clientes, suspenda o regularice (estado R) manualmente si corresponde.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" id="btn-regularizar-arca-modal" class="btn btn-warning d-none js-btn-regularizar-cliente">
                    <i class="fa fa-check-circle"></i> Regularizar cliente (R)
                </button>
                <button type="button" class="btn btn-primary" data-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
