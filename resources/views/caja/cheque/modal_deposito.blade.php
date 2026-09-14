<div class="modal fade" id="modalDepositoCheque" tabindex="-1" role="dialog" aria-labelledby="modalDepositoChequeLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDepositoChequeLabel">Depositar cheque de terceros</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deposito-modo-masivo" value="0" />
                <p class="mb-2">Cheque: <strong id="deposito-cheque-ref"></strong></p>
                <div class="form-group">
                    <label for="deposito_fecha">Fecha depósito</label>
                    <input type="date" id="deposito_fecha" class="form-control form-control-sm" />
                </div>
                <div class="form-group">
                    <label for="deposito_cuentacaja_id">Cuenta de caja / banco</label>
                    <select id="deposito_cuentacaja_id" class="form-control form-control-sm">
                        <option value="">-- Seleccionar --</option>
                        @foreach (($cuentacaja_deposito_query ?? []) as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="deposito_nro_boleta">Nro. boleta (opcional)</label>
                    <input type="text" id="deposito_nro_boleta" maxlength="40" class="form-control form-control-sm" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="deposito_cheque_confirmar">
                    <i class="fa fa-university"></i> Confirmar depósito
                </button>
            </div>
        </div>
    </div>
</div>
