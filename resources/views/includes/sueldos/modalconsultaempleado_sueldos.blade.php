@once('anita-modal-consulta-empleado-sueldos')
<div class="modal fade" id="consultaempleado_sueldosModal" role="dialog"
     aria-labelledby="consultaempleado_sueldosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consultaempleado_sueldosModalLabel">Empleados</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="consultaempleado_sueldos" class="col-form-label col-md-2">Buscar:</label>
                    <div class="col-md-10">
                        <input type="text" id="consultaempleado_sueldos" class="form-control"
                               autocomplete="off" placeholder="Legajo, nombre, documento o CUIL">
                    </div>
                </div>
                <p class="text-muted small mb-2">Solo empleados activos de la empresa seleccionada.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered table-hover">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Legajo</th>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>CUIL</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="datosempleado_sueldos"></tbody>
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
