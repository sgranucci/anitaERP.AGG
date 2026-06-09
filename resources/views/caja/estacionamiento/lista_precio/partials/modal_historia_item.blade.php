<div class="modal fade" id="modalHistoriaPrecioItem" tabindex="-1" role="dialog" aria-labelledby="modalHistoriaPrecioItemTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalHistoriaPrecioItemTitulo">Historial de precios</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-2">
                <p class="mb-2 small text-muted" id="modalHistoriaPrecioItemSubtitulo"></p>
                <div id="modalHistoriaPrecioItemCargando" class="text-center text-muted py-3 d-none">
                    <span class="fa fa-spinner fa-spin mr-2"></span>Cargando…
                </div>
                <div id="modalHistoriaPrecioItemError" class="alert alert-danger py-2 d-none"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Vigente desde</th>
                                <th class="text-right">Precio</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="modalHistoriaPrecioItemBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
