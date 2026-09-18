<div class="modal fade" id="modalDepositoCheque" tabindex="-1" role="dialog" aria-labelledby="modalDepositoChequeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDepositoChequeLabel">Depositar cheque de terceros</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deposito-modo-masivo" value="0" />
                <input type="hidden" id="empresa_id" value="" />
                <p class="mb-2">Cheque: <strong id="deposito-cheque-ref"></strong></p>
                <p class="mb-2 d-none" id="deposito-cheque-total-wrap">
                    Total a depositar: <strong id="deposito-cheque-total"></strong>
                </p>
                <div class="form-group">
                    <label for="deposito_fecha">Fecha depósito</label>
                    <input type="date" id="deposito_fecha" class="form-control form-control-sm" />
                </div>
                @include('caja.partials.campo_consulta_cuentacaja', [
                    'prefix' => 'deposito',
                    'label' => 'Cuenta de caja / banco',
                    'inputName' => 'cuentacaja_id',
                    'inputId' => 'deposito_cuentacaja_id',
                    'layout' => 'form_row',
                    'col_label' => 'col-12',
                    'col_input' => 'col-12',
                    'required' => true,
                    'mostrar_editar' => true,
                    'ayuda' => 'Código + Enter, o F1 / lupa para consultar.',
                ])
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
