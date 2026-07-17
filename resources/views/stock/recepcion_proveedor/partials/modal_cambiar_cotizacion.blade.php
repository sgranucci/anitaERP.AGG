@if (can('cambiar-cotizacion-recepcion-proveedor', false))
<div class="modal fade" id="modal-cambiar-cotizacion" tabindex="-1" role="dialog" aria-labelledby="modal-cambiar-cotizacion-titulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" id="form-cambiar-cotizacion" action=""
              data-action-template="{{ route('cambiar_cotizacion_recepcion_proveedor', ['id' => '__ID__']) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-cambiar-cotizacion-titulo">
                        <i class="fas fa-dollar-sign"></i> Cambiar cotización
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Recepción Nº <strong id="cambiar-cotizacion-numero"></strong></p>
                    <p class="text-muted small mb-3">
                        Se actualiza <strong>solo la cotización</strong> en la recepción, el asiento contable del ERP
                        y en Anita (ctamov y recepmov). No se modifican cantidades ni precios.
                    </p>
                    <div class="form-group mb-0">
                        <label for="cambiar-cotizacion-valor">Nueva cotización</label>
                        <input type="number" step="0.000001" min="0.000001"
                               class="form-control" id="cambiar-cotizacion-valor" name="cotizacion" required autocomplete="off">
                        <small class="form-text text-muted">
                            Cotización actual: <span id="cambiar-cotizacion-actual"></span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-cambiar-cotizacion-guardar">
                        <i class="fa fa-save"></i> Actualizar cotización
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
