{{-- Modal: recalcular transferencias a depósito fórmulas por cambio de coeficiente --}}
<div class="modal fade" id="modalRecalcularTransferenciasFormula" tabindex="-1" role="dialog" aria-labelledby="modalRecalcularTransferenciasFormulaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalRecalcularTransferenciasFormulaLabel">
                    <i class="fa fa-sync text-warning"></i> Recalcular transferencias a f&oacute;rmulas
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body py-3">
                <p class="small mb-2" id="rtf-articulo-info"></p>
                <div class="form-row align-items-end">
                    <div class="form-group col-md-2 mb-2">
                        <label class="small mb-0" for="rtf-coeficiente">Coeficiente</label>
                        <input type="number" id="rtf-coeficiente" class="form-control form-control-sm" step="any" min="0.000001">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0 d-block">Alcance</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="rtf-modo-ultima" name="rtf-modo" class="custom-control-input" value="ultima" checked>
                            <label class="custom-control-label small" for="rtf-modo-ultima">&Uacute;ltima TRA</label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" id="rtf-modo-rango" name="rtf-modo" class="custom-control-input" value="rango">
                            <label class="custom-control-label small" for="rtf-modo-rango">Rango de fechas</label>
                        </div>
                    </div>
                    <div class="form-group col-md-2 mb-2 rtf-rango-campos d-none">
                        <label class="small mb-0" for="rtf-fecha-desde">Desde</label>
                        <input type="date" id="rtf-fecha-desde" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-2 mb-2 rtf-rango-campos d-none">
                        <label class="small mb-0" for="rtf-fecha-hasta">Hasta</label>
                        <input type="date" id="rtf-fecha-hasta" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <button type="button" id="rtf-btn-preview" class="btn btn-info btn-sm">
                            <i class="fa fa-search"></i> Vista previa
                        </button>
                    </div>
                </div>
                <p class="small text-muted mb-2">
                    Conserva el costo de origen de cada TRA y recalcula cantidad/costo del insumo destino
                    (<code>destino = origen / coef</code>, <code>cant destino = cant origen &times; coef</code>).
                    Al aplicar, actualiza movimientos de stock vinculados y Anita <code>stkmae</code> (precio compra3) por empresa de la TRA.
                    Por defecto el alcance es la &uacute;ltima TRA; us&aacute; <strong>Rango de fechas</strong> para Biyemas/Kandiko/Rebisco en el per&iacute;odo.
                </p>
                <div id="rtf-loading" class="small text-muted d-none">
                    <i class="fa fa-spinner fa-spin"></i> Consultando…
                </div>
                <div id="rtf-error" class="small text-danger d-none"></div>
                <div id="rtf-vacio" class="small text-muted d-none">No hay transferencias confirmadas a f&oacute;rmulas para este art&iacute;culo.</div>
                <div id="rtf-sin-cambio" class="alert alert-info py-2 small d-none mb-2">
                    Las TRA listadas ya coinciden con el coeficiente indicado. Marc&aacute; filas manualmente si quer&eacute;s forzar el rec&aacute;lculo,
                    o cambi&aacute; el coeficiente / us&aacute; <strong>Rango de fechas</strong> para revisar otras.
                </div>
                <div class="table-responsive d-none" id="rtf-tabla-wrap" style="max-height: 22rem;">
                    <div class="mb-1">
                        <button type="button" id="rtf-check-cambios" class="btn btn-outline-secondary btn-xs btn-sm py-0">Solo con diferencias</button>
                        <button type="button" id="rtf-check-todas" class="btn btn-outline-secondary btn-xs btn-sm py-0">Seleccionar todas</button>
                        <span class="small text-muted ml-2" id="rtf-seleccion-hint"></span>
                    </div>
                    <table class="table table-sm table-bordered table-hover mb-1">
                        <thead style="background-color:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:2rem"></th>
                                <th>Fecha</th>
                                <th>TRA</th>
                                <th>Empresa</th>
                                <th>Dep. destino</th>
                                <th>Insumo</th>
                                <th class="text-right">Coef</th>
                                <th class="text-right">Cant. dest.</th>
                                <th class="text-right">Costo dest.</th>
                            </tr>
                        </thead>
                        <tbody id="rtf-tbody"></tbody>
                    </table>
                    <p class="small mb-0" id="rtf-resumen"></p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                <button type="button" id="rtf-btn-aplicar" class="btn btn-warning btn-sm" disabled>
                    <i class="fa fa-check"></i> Aplicar cambios
                </button>
            </div>
        </div>
    </div>
</div>
