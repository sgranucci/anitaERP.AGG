<div class="modal fade" id="modalFormulasUsoArticulo" tabindex="-1" role="dialog" aria-labelledby="modalFormulasUsoArticuloTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalFormulasUsoArticuloTitulo">F&oacute;rmulas que usan el art&iacute;culo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="modalFormulasUsoArticuloSubtitulo">
                    F&oacute;rmulas que incluyen este art&iacute;culo como componente o como subf&oacute;rmula.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:8%">F&oacute;rmula</th>
                                <th style="width:14%">SKU</th>
                                <th>Art&iacute;culo de la f&oacute;rmula</th>
                                <th>Detalle</th>
                                <th style="width:8%" class="text-right">Cantidad</th>
                                <th style="width:10%">Uso</th>
                                <th style="width:8%">Opcional</th>
                                <th style="width:10%">Estado</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-formulas-uso-articulo">
                            <tr><td colspan="8" class="text-muted">Cargando…</td></tr>
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
