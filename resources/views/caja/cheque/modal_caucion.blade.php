<div class="modal fade" id="modalCaucionCheque" tabindex="-1" role="dialog" aria-labelledby="modalCaucionChequeLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCaucionChequeLabel">Caucionar cheque de terceros</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="caucion-modo-masivo" value="0" />
                <p class="mb-2">Cheque: <strong id="caucion-cheque-ref"></strong></p>
                <div class="form-group">
                    <label for="caucion_nro">Nro. caución</label>
                    <input type="text" id="caucion_nro" maxlength="20" class="form-control form-control-sm" />
                </div>
                <div class="form-group mb-0">
                    <label for="caucion_fecha">Fecha caución</label>
                    <input type="date" id="caucion_fecha" class="form-control form-control-sm" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning btn-sm" id="caucion_cheque_confirmar">
                    <i class="fa fa-lock"></i> Confirmar caución
                </button>
            </div>
        </div>
    </div>
</div>
