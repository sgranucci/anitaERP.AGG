<div class="modal fade" id="modal-anular-rango-rend-bingo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content" style="max-height:calc(100vh - 3.5rem);">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Anular cierre contable por rango de fechas</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="overflow-y:auto;">
                <p class="text-muted small">
                    Anula jornadas de bingo <strong>ya cerradas</strong> en el rango.
                    Borra <strong>físicamente</strong> los asientos en ERP y <strong>ctamov</strong> Anita,
                    anula el FBI y limpia el pozo acumulado desde la primera jornada anulada.
                </p>
                <div class="alert alert-warning py-2 small">
                    <i class="fa fa-exclamation-triangle"></i>
                    Se anula de la jornada más nueva hacia la más vieja.
                    No puede quedar un cierre posterior al «hasta» (el pozo se borra desde el inicio del rango).
                </div>
                <div class="form-group">
                    <label for="anular-rango-empresa-id">Empresa</label>
                    <select id="anular-rango-empresa-id" class="form-control">
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
                        <label for="anular-rango-fecha-desde">Jornada desde</label>
                        <input type="date" id="anular-rango-fecha-desde" class="form-control">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="anular-rango-fecha-hasta">Jornada hasta</label>
                        <input type="date" id="anular-rango-fecha-hasta" class="form-control">
                    </div>
                </div>
                <div id="anular-rango-preview-box" class="d-none">
                    <div class="alert alert-info mb-2" id="anular-rango-preview-resumen"></div>
                    <p class="small text-muted mb-1">
                        Una jornada cerrada por fila. Use <i class="fa fa-chevron-down"></i> para ver cada rendici&oacute;n.
                    </p>
                    <div class="table-responsive" style="max-height:220px;overflow-y:auto;-webkit-overflow-scrolling:touch;">
                        <table class="table table-sm table-bordered mb-0" id="tabla-anular-rango-preview-grupos">
                            <thead style="background:#F5B7B1;color:#17202A;">
                                <tr>
                                    <th class="width30"></th>
                                    <th>Jornada</th>
                                    <th>FBI / asiento</th>
                                    <th class="text-center">Rend.</th>
                                    <th class="text-right">Recaudaci&oacute;n</th>
                                </tr>
                            </thead>
                            <tbody id="anular-rango-preview-tbody"></tbody>
                        </table>
                    </div>
                    <div id="anular-rango-preview-por-dia-box" class="mt-3 d-none">
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
                                <tbody id="anular-rango-preview-por-dia-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div id="anular-rango-error-box" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-primary" id="btn-anular-rango-preview">
                    <i class="fa fa-eye"></i> Ver cerrados
                </button>
                <button type="button" class="btn btn-danger d-none" id="btn-anular-rango-ejecutar">
                    <i class="fa fa-unlock"></i> Confirmar anulación del rango
                </button>
            </div>
        </div>
    </div>
</div>
