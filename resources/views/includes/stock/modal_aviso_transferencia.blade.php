{{--
    Modal para tipos de transferencia con "aviso opcional": al grabar se pregunta
    si se env&iacute;a el aviso de aprobaci&oacute;n al dep&oacute;sito destino.
    - S&iacute;: transferencia queda pendiente con el canal de aviso ya armado.
    - No: transferencia directa (confirmada, sin aviso ni aprobaci&oacute;n).
    Compartido por Movimientos de stock y Transferencia de mercader&iacute;a.
--}}
<div class="modal fade" id="modalAvisoTransferencia" tabindex="-1" role="dialog"
    aria-labelledby="modalAvisoTransferenciaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalAvisoTransferenciaTitulo">
                    <i class="fa fa-bell"></i> Transferencia con aviso
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    El tipo de transacci&oacute;n seleccionado permite enviar un aviso de aprobaci&oacute;n al dep&oacute;sito destino.
                </p>
                <p class="mb-0">
                    <strong>&iquest;Desea enviar el aviso?</strong>
                    Si elige <em>No</em>, la transferencia se registra de forma directa (sin aviso ni aprobaci&oacute;n).
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="btn_aviso_transferencia_cancelar" data-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-outline-primary" id="btn_aviso_transferencia_no">
                    <i class="fa fa-bolt"></i> No, transferir directo
                </button>
                <button type="button" class="btn btn-primary" id="btn_aviso_transferencia_si">
                    <i class="fa fa-paper-plane"></i> S&iacute;, enviar aviso
                </button>
            </div>
        </div>
    </div>
</div>
