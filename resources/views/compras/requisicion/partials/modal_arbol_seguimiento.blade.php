<div class="modal fade" id="modalRequisicionArbolSeguimiento" tabindex="-1" role="dialog"
     aria-labelledby="modalRequisicionArbolSeguimientoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRequisicionArbolSeguimientoTitulo">
                    <i class="fa fa-sitemap"></i> Árbol de aprobación
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="requisicionArbolSeguimientoAviso" class="d-none mb-2"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Envío</th>
                                <th>Envió</th>
                                <th class="text-center">Nivel</th>
                                <th>Estado</th>
                                <th>Proceso</th>
                                <th>Destinatario</th>
                                <th>Obs.</th>
                            </tr>
                        </thead>
                        <tbody id="requisicionArbolSeguimientoCuerpo">
                            <tr>
                                <td colspan="7" class="text-center text-muted">Cargando…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
