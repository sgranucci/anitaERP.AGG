<div class="modal fade" id="modalArticulosCompraInsumo" tabindex="-1" role="dialog" aria-labelledby="modalArticulosCompraInsumoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalArticulosCompraInsumoTitulo">Art&iacute;culos de compra del insumo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="modalArticulosCompraInsumoSubtitulo">
                    Art&iacute;culos de compra cuyo campo <em>SKU alt./insumo</em> apunta al insumo de la l&iacute;nea.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:8%">ID</th>
                                <th style="width:14%">SKU</th>
                                <th>Descripci&oacute;n</th>
                                <th style="width:16%">SKU alt./insumo</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-articulos-compra-insumo-modal">
                            <tr><td colspan="4" class="text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
