@if ($puede_proceso_cierre ?? false)
    <hr class="my-4">
    <h4 class="mb-2">
        <i class="fa fa-calculator"></i> Proceso de cierre — redistribución, asientos y facturación
    </h4>
    <p class="text-muted small">
        Continúa la auditoría Waitry con el tramo desde el último ticket cerrado hasta el cierre de jornada.
        Solo <strong>administrador</strong> y <strong>encargado de tesorería</strong> pueden ejecutar este proceso.
        El efectivo en Waitry (<code>cash</code>) <strong>no se factura</strong>.
    </p>

    @if (! ($proceso_habilitado ?? false))
        <div class="alert alert-warning py-2">
            Proceso no disponible: requiere <code>WAITRY_HABILITADO</code> y cierre tótem de jornada habilitado.
        </div>
    @else
        <div class="mb-2">
            <button type="button" class="btn btn-warning btn-sm" id="btn-proceso-analizar"
                    @disabled(! ($consultado ?? false))>
                <i class="fa fa-balance-scale"></i> Analizar tramo de jornada (Waitry vs Anita)
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-config-contable" title="Cuentas contables">
                <i class="fa fa-cog"></i> Configuración contable
            </button>
        </div>

        <div id="proceso-loading" class="alert alert-info d-none py-2">
            <i class="fa fa-spinner fa-spin"></i> Procesando movimientos Waitry…
        </div>
        <div id="proceso-error" class="alert alert-danger d-none py-2"></div>

        <div id="panel-proceso-meta" class="d-none mb-2">
            <p class="mb-1 small"><strong>Ventana:</strong> <span id="meta-ventana"></span></p>
            <p class="mb-0 small"><strong>Rango Waitry:</strong> <span id="meta-rango"></span></p>
        </div>

        <div id="panel-proceso-notas" class="d-none">
            <ul class="text-muted small mb-2" id="lista-notas"></ul>
        </div>

        <div id="panel-proceso-grilla" class="d-none">
            <div class="table-responsive mb-2">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>QR sin facturar</th>
                            <th>QR fact. Anita</th>
                            <th>MP fact. Anita</th>
                            <th>Efectivo fact. Anita</th>
                            <th>Total facturación (base %)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-right" id="celda-qr-sin"></td>
                            <td class="text-right" id="celda-qr-fact"></td>
                            <td class="text-right" id="celda-mp-fact"></td>
                            <td class="text-right" id="celda-efe-fact"></td>
                            <td class="text-right font-weight-bold" id="celda-total-fact"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="form-inline mb-2">
                <label for="input-porcentaje" class="mr-2 small">Porcentaje</label>
                <input type="number" id="input-porcentaje" class="form-control form-control-sm" style="width:90px"
                       min="0" max="100" step="0.01" value="0">
                <span class="ml-1 mr-2 small">%</span>
                <button type="button" class="btn btn-sm btn-primary" id="btn-proceso-recalcular">
                    Recalcular medios y preview de asientos
                </button>
                <span class="ml-2 text-muted small" id="label-objetivo-importe"></span>
            </div>
        </div>

        <div id="panel-proceso-grupos" class="d-none">
            <h6 class="mt-2">Grupos (detalle paginado)</h6>
            <div id="acordeon-grupos"></div>
        </div>

        <div id="panel-proceso-asientos" class="d-none mt-3">
            <h6>Preview de asientos (sin grabar)</h6>
            <div id="asientos-advertencias" class="mb-1"></div>
            <p class="small mb-1">
                <strong>Debe:</strong> <span id="asientos-debe"></span>
                · <strong>Haber:</strong> <span id="asientos-haber"></span>
            </p>
            <div id="lista-asientos"></div>
            <p class="text-muted small mt-2 mb-0">
                La facturación masiva y el grabado de asientos se habilitará tras validar este preview.
            </p>
        </div>
    @endif

    <div class="modal fade" id="modal-config-contable" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Cuentas contables — cierre Waitry</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <form id="form-config-contable">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="cfg_cuenta_ventas_id">Cuenta ventas</label>
                            <input type="number" class="form-control" id="cfg_cuenta_ventas_id" name="cuenta_ventas_id" min="1">
                        </div>
                        <div class="form-group">
                            <label for="cfg_cuenta_iva_id">Cuenta IVA (débito fiscal)</label>
                            <input type="number" class="form-control" id="cfg_cuenta_iva_id" name="cuenta_iva_id" min="1">
                        </div>
                        <div class="form-group">
                            <label for="cfg_cuenta_impuesto_interno_id">Cuenta impuesto interno</label>
                            <input type="number" class="form-control" id="cfg_cuenta_impuesto_interno_id"
                                   name="cuenta_impuesto_interno_id" min="1">
                        </div>
                        <div class="form-group mb-0">
                            <label for="cfg_cuenta_fondo_fijo_id">Fondo fijo máquinas (ref.)</label>
                            <input type="number" class="form-control" id="cfg_cuenta_fondo_fijo_maquinas_id"
                                   name="cuenta_fondo_fijo_maquinas_id" min="1">
                        </div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
