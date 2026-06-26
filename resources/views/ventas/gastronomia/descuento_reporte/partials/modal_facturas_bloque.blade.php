<div class="modal fade" id="modalFacturasDescuentoReporte" tabindex="-1" role="dialog"
     aria-labelledby="modalFacturasDescuentoReporteTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalFacturasDescuentoReporteTitulo">Facturas del total</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="modal-facturas-descuento-cargando" class="text-center py-4 text-muted">
                    <i class="fa fa-spinner fa-spin fa-2x mb-2 d-block"></i>
                    Cargando facturas…
                </div>
                <div id="modal-facturas-descuento-error" class="alert alert-danger m-3 d-none"></div>
                <div class="table-responsive d-none" id="modal-facturas-descuento-wrap">
                    <table class="table table-sm table-striped table-bordered mb-0" id="tabla-facturas-descuento-bloque">
                        <thead style="background-color: #85C1E9; color: #17202A;">
                            <tr>
                                <th>Jornada</th>
                                <th>Comprobante</th>
                                <th>C&oacute;digo</th>
                                <th class="text-right">Total l&iacute;neas</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-facturas-descuento-bloque"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <span class="small text-muted mr-auto" id="modal-facturas-descuento-contador"></span>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
