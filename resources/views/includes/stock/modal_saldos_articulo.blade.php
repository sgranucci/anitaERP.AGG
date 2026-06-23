@once('anita-modal-saldos-articulo')
{{-- Modal: saldos por depósito con acceso directo al kardex --}}
<div class="modal fade" id="modalSaldosArticulo" tabindex="-1" role="dialog" aria-labelledby="modalSaldosArticuloLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalSaldosArticuloLabel">
                    <i class="fa fa-warehouse text-secondary"></i> Saldos de stock
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="small mb-2" id="modal-saldos-articulo-info"></p>
                @include('stock.partials.saldos_articulo_deposito_panel', ['panelId' => 'modal'])
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" id="btn-saldos-kardex-todos" class="btn btn-primary btn-sm">
                    <i class="fa fa-list-alt"></i> Kardex todos los dep&oacute;sitos
                </button>
            </div>
        </div>
    </div>
</div>
@endonce
