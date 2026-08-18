@once('anita-modal-consulta-liquidacion-sueldos')
<div class="modal fade" id="consultaliquidacion_sueldosModal" role="dialog"
     aria-labelledby="consultaliquidacion_sueldosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultaliquidacion_sueldosModalLabel">Liquidaciones de sueldos</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consultaliquidacion_sueldos" class="col-form-label mr-2">Buscar:</label>
                    <input type="text" id="consultaliquidacion_sueldos" autocomplete="off" autofocus>
                </div>
                <p class="text-muted small mb-2">
                    Busque por n&uacute;mero, per&iacute;odo, descripci&oacute;n, tipo o estado.
                    Enter elige la primera fila.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>ID</th>
                                <th>N&uacute;mero</th>
                                <th>Per&iacute;odo / descripci&oacute;n</th>
                                <th>Tipo</th>
                                <th>Empresa</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="datosliquidacion_sueldos"></tbody>
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
