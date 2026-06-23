@once('anita-modal-kardex-deposito')
{{-- Modal: elegir depósito antes de abrir kardex (mov. stock, ABM sin dep. entrega, etc.) --}}
<div class="modal fade" id="modalKardexDeposito" tabindex="-1" role="dialog" aria-labelledby="modalKardexDepositoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalKardexDepositoLabel">
                    <i class="fa fa-list-alt text-primary"></i> Kardex de stock
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="small mb-2" id="modal-kardex-articulo-info"></p>
                <div id="modal-kardex-saldos-wrap" class="mb-3">
                    @include('stock.partials.saldos_articulo_deposito_panel', ['panelId' => 'kardex'])
                </div>
                <div id="modal-kardex-opciones-wrap" class="mb-3 d-none">
                    <label class="d-block small font-weight-bold mb-1">Elija dep&oacute;sito</label>
                    <div id="modal-kardex-opciones" class="list-group"></div>
                </div>
                <div id="modal-kardex-picker-wrap">
                    <label class="d-block small font-weight-bold mb-1" id="modal-kardex-picker-label">Dep&oacute;sito</label>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'kardex_pick',
                        'layout' => 'inline',
                        'label' => '',
                        'inputName' => 'kardex_pick_deposito_id',
                        'inputId' => 'kardex_pick_deposito_id',
                        'depositoId' => '',
                        'codigo' => '',
                        'descripcion' => '',
                        'required' => false,
                        'solo_lectura' => false,
                        'mostrar_editar' => false,
                    ])
                </div>
                <button type="button" id="btn-kardex-todos-depositos" class="btn btn-link btn-sm px-0 mt-2">
                    Ver todos los dep&oacute;sitos autorizados
                </button>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-kardex-abrir" class="btn btn-primary btn-sm">
                    <i class="fa fa-external-link-alt"></i> Abrir kardex
                </button>
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@endonce
