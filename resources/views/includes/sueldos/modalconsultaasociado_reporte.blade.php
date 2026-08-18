@once('anita-modal-consulta-asociado-reporte')
<div class="modal fade" id="consultaasociado_reporteModal" role="dialog"
     aria-labelledby="consultaasociado_reporteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultaasociado_reporteModalLabel">Consultar asociado</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consultaasociado_reporte" class="col-form-label mr-2">Buscar:</label>
                    <input type="text" id="consultaasociado_reporte" autocomplete="off" autofocus>
                </div>
                <p class="text-muted small mb-2">
                    Busque por c&oacute;digo, descripci&oacute;n o n&uacute;mero. Enter elige la primera fila.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>C&oacute;digo</th>
                                <th>Descripci&oacute;n</th>
                                <th>N&uacute;mero</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="datosasociado_reporte"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endonce
