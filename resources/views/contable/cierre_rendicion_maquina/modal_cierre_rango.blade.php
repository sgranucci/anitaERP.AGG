<div class="modal fade" id="modal-cierre-rango-rend-maquina" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content" style="max-height:calc(100vh - 3.5rem);">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Cierre contable por rango de fechas</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="overflow-y:auto;">
                <p class="text-muted small">
                    Cierra jornadas de m&aacute;quinas <strong>pendientes</strong> en el rango.
                    Se genera <strong>un cierre diario por fecha jornada</strong> (FSL + asientos).
                    El <strong>desde</strong> debe ser la jornada pendiente m&aacute;s antigua.
                </p>
                <div class="alert alert-warning py-2 small">
                    <i class="fa fa-link"></i>
                    Correlatividad obligatoria: no se puede saltear fechas pendientes anteriores.
                </div>
                <div class="form-group">
                    <label for="rango-empresa-id">Empresa</label>
                    <select id="rango-empresa-id" class="form-control">
                        <option value="">— Seleccione —</option>
                        @foreach ($empresa_query as $emp)
                            <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                {{ $emp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="rango-fecha-desde">Jornada desde</label>
                        <input type="date" id="rango-fecha-desde" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="rango-fecha-hasta">Jornada hasta</label>
                        <input type="date" id="rango-fecha-hasta" class="form-control">
                    </div>
                </div>
                <div id="rango-preview-box" class="d-none">
                    <div class="alert alert-info mb-2" id="rango-preview-resumen"></div>
                    <p class="small text-muted mb-1">
                        Una jornada por fila. Use <i class="fa fa-chevron-down"></i> para ver cada rendici&oacute;n.
                    </p>
                    <div class="table-responsive" style="max-height:220px;overflow-y:auto;-webkit-overflow-scrolling:touch;">
                        <table class="table table-sm table-bordered mb-0" id="tabla-rango-preview-grupos">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th class="width30"></th>
                                    <th>Jornada</th>
                                    <th>Detalle</th>
                                    <th class="text-center">Rend.</th>
                                    <th class="text-right">Recaudaci&oacute;n</th>
                                </tr>
                            </thead>
                            <tbody id="rango-preview-tbody"></tbody>
                        </table>
                    </div>
                    <div id="rango-preview-por-dia-box" class="mt-3 d-none">
                        <p class="small font-weight-bold mb-1">Totales por jornada</p>
                        <div class="table-responsive" style="max-height:160px;overflow-y:auto;-webkit-overflow-scrolling:touch;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Jornada</th>
                                        <th class="text-center">Rend.</th>
                                        <th class="text-center">Cierres</th>
                                        <th class="text-right">Recaudaci&oacute;n</th>
                                    </tr>
                                </thead>
                                <tbody id="rango-preview-por-dia-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div id="rango-error-box" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-primary" id="btn-rango-preview">
                    <i class="fa fa-eye"></i> Ver pendientes
                </button>
                <button type="button" class="btn btn-success d-none" id="btn-rango-ejecutar">
                    <i class="fa fa-lock"></i> Confirmar cierre del rango
                </button>
            </div>
        </div>
    </div>
</div>
