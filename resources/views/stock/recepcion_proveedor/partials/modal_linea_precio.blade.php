{{-- Modal detalle de precios por línea de recepción --}}
<div class="modal fade" id="modalRecepcionLineaPrecio" tabindex="-1" role="dialog"
    aria-labelledby="modalRecepcionLineaPrecioTitulo" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light py-2">
                <h5 class="modal-title" id="modalRecepcionLineaPrecioTitulo">Precios de la l&iacute;nea</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="modal-linea-precio-subtitulo"></p>
                <input type="hidden" id="modal-linea-precio-idx" value="">
                <div class="form-group row mb-2">
                    <label class="col-sm-5 col-form-label col-form-label-sm text-muted">Precio OC (original)</label>
                    <div class="col-sm-7">
                        <input type="text" class="form-control form-control-sm text-right" id="modal-linea-precio-oc" readonly>
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-5 col-form-label col-form-label-sm">Cantidad recibida</label>
                    <div class="col-sm-7">
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.000001" min="0" class="form-control text-right" id="modal-linea-cantidad">
                            <div class="input-group-append">
                                <span class="input-group-text" id="modal-linea-um-compra">—</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group row mb-2">
                    <label class="col-sm-5 col-form-label col-form-label-sm" id="modal-linea-precio-unit-label">Precio recepci&oacute;n (unit.)</label>
                    <div class="col-sm-7">
                        <input type="number" step="0.000001" min="0" class="form-control form-control-sm text-right" id="modal-linea-precio-unit">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label class="col-sm-5 col-form-label col-form-label-sm font-weight-bold">Total l&iacute;nea</label>
                    <div class="col-sm-7">
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-right font-weight-bold" id="modal-linea-importe">
                    </div>
                </div>
                <div class="form-group row mb-0 mt-3 d-none" id="modal-linea-precio-comentario-wrap">
                    <label class="col-sm-5 col-form-label col-form-label-sm text-warning font-weight-bold" for="modal-linea-comentario-precio">
                        Motivo diferencia <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-7">
                        <input type="text" class="form-control form-control-sm" id="modal-linea-comentario-precio" maxlength="255"
                            placeholder="Obligatorio si el precio difiere de la OC">
                    </div>
                </div>
                <small class="form-text text-muted mt-2">
                    Al cambiar el total se recalcula el precio unitario (total &divide; cantidad). Al cambiar el precio unitario se actualiza el total.
                </small>
                <div class="alert alert-warning py-2 mt-3 mb-0 d-none" id="modal-linea-precio-diff-aviso"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-modal-linea-precio-aplicar">
                    <i class="fa fa-check"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</div>
