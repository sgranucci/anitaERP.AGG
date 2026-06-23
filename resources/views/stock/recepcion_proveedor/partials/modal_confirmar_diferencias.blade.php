<div class="modal fade" id="modalConfirmarRecepcionDiferencias" tabindex="-1" role="dialog" aria-labelledby="modalConfirmarRecepcionDiferenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="modalConfirmarRecepcionDiferenciasLabel">
                    <i class="fa fa-exclamation-triangle"></i> Confirmar recepci&oacute;n — revisi&oacute;n de l&iacute;neas
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Se generar&aacute; movimiento de stock y asiento contable. Revise las l&iacute;neas con diferencias antes de confirmar.
                </p>
                <div id="modal-confirmar-recepcion-resumen"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Volver a editar</button>
                <button type="button" class="btn btn-success" id="btn-modal-confirmar-recepcion-aceptar">
                    <i class="fa fa-check"></i> Confirmar recepci&oacute;n
                </button>
            </div>
        </div>
    </div>
</div>
