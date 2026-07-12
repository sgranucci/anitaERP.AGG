<div class="modal fade" id="modalAccionLineasSinCantidad" tabindex="-1" role="dialog" aria-labelledby="modalAccionLineasSinCantidadLabel" aria-hidden="true" data-backdrop="static" data-keyboard="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modalAccionLineasSinCantidadLabel">
                    <i class="fa fa-question-circle"></i> L&iacute;neas sin cantidad en este remito
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Hay l&iacute;neas de la OC sin cantidad recibida ni rechazada en este remito. Indique si quedan <strong>pendientes</strong> para otra entrega o si desea <strong>cerrar</strong> la l&iacute;nea en la OC (con comentario obligatorio).
                    Si recibe una cantidad parcial, use los botones <strong>Pendiente</strong> / <strong>Cerrar saldo</strong> debajo de la l&iacute;nea.
                </p>
                <div id="modal-accion-lineas-sin-cantidad-lista"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Volver a editar</button>
                <button type="button" class="btn btn-primary" id="btn-modal-accion-lineas-aplicar">
                    <i class="fa fa-save"></i> Aplicar y guardar
                </button>
            </div>
        </div>
    </div>
</div>
<style>
    #modalAccionLineasSinCantidad .modal-body,
    #modalAccionLineasSinCantidad .modal-content {
        overflow: visible;
    }
    #modalAccionLineasSinCantidad .modal-accion-linea-fila {
        background: #fff;
    }
    #modalAccionLineasSinCantidad .modal-accion-linea-comentario {
        pointer-events: auto;
        resize: vertical;
        min-height: 2.25rem;
    }
    #modalAccionLineasSinCantidad .modal-accion-linea-opt.active {
        box-shadow: inset 0 0 0 2px rgba(0, 0, 0, 0.2);
    }
</style>
