<div class="modal fade" id="modalArticulosFormula" tabindex="-1" role="dialog" aria-labelledby="modalArticulosFormulaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalArticulosFormulaTitulo">Art&iacute;culos con esta f&oacute;rmula</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Listado de art&iacute;culos cuyo campo <em>f&oacute;rmula</em> referencia esta definici&oacute;n (puede incluir el de cabecera si est&aacute; vinculado).</p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:8%">ID</th>
                                <th style="width:14%">SKU</th>
                                <th>Descripci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-articulos-formula-modal">
                            <tr><td colspan="3" class="text-muted">Cargando…</td></tr>
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
