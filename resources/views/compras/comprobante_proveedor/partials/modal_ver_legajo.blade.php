<div class="modal fade" id="modalCpVerLegajo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 1100px;">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Legajo OC</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="cp-legajo-anticipada-banner" class="alert alert-warning py-2 small d-none mb-3">
                    <i class="fa fa-clock-o"></i> <strong>Legajo anticipado</strong>
                    — puede haber más de una factura (anticipo) en el mismo legajo.
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary mb-2"><i class="fa fa-file-text-o"></i> Facturas / precargas</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 52%;">
                                    <col style="width: 22%;">
                                    <col style="width: 26%;">
                                </colgroup>
                                <thead><tr><th>Documento</th><th>Fecha</th><th class="text-right">Total</th></tr></thead>
                                <tbody id="cp-legajo-facturas-body">
                                    <tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary mb-2"><i class="fa fa-check-square-o"></i> Comprobantes ERP</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 40%;">
                                    <col style="width: 30%;">
                                    <col style="width: 30%;">
                                </colgroup>
                                <thead><tr><th>Documento</th><th>Estado</th><th class="text-right">Total</th></tr></thead>
                                <tbody id="cp-legajo-comprobantes-body">
                                    <tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary mb-2"><i class="fa fa-truck"></i> Recepciones COM</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 46%;">
                                    <col style="width: 24%;">
                                    <col style="width: 30%;">
                                </colgroup>
                                <thead><tr><th>Documento</th><th>Fecha</th><th>Estado</th></tr></thead>
                                <tbody id="cp-legajo-coms-body">
                                    <tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary mb-2"><i class="fa fa-undo"></i> Devoluciones</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 46%;">
                                    <col style="width: 24%;">
                                    <col style="width: 30%;">
                                </colgroup>
                                <thead><tr><th>Documento</th><th>Fecha</th><th>Estado</th></tr></thead>
                                <tbody id="cp-legajo-devoluciones-body">
                                    <tr><td colspan="3" class="text-muted text-center">Cargando…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <a href="#" id="cp-legajo-abrir-oc" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    <i class="fa fa-external-link"></i> Abrir OC
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
