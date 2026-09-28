<div class="modal fade" id="modal-asignar-picking" role="dialog" aria-labelledby="modal-asignar-picking-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-asignar-picking-label">Asignar al picking</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Hay más de un picking sin facturar. Elegí en cuál entra esta línea.
                    Un picking deja de figurar cuando se factura.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>N&deg;</th>
                                <th>Fecha</th>
                                <th class="text-right">L&iacute;neas pendientes</th>
                                <th>Clientes</th>
                                <th style="width:1%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="modal-asignar-picking-filas"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-asignar-picking-nuevo">
                    <i class="fa fa-plus"></i> Nuevo picking
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
            </div>
        </div>
    </div>
</div>
