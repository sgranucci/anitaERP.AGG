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
            <div class="alert alert-secondary py-2 mb-0">
                <p class="mb-1 small"><strong>Ventana operativa:</strong> <span id="meta-ventana"></span></p>
                <p class="mb-1 small"><strong>Rango calendario Waitry (API):</strong> <span id="meta-rango"></span></p>
                <p class="mb-1 small"><strong>Órdenes incluidas:</strong> <span id="meta-cantidad"></span></p>
                <p class="mb-1 small d-none" id="meta-canceladas-wrap">
                    <strong>Waitry canceladas (excluidas del cuadro):</strong>
                    <span id="meta-canceladas"></span>
                </p>
                <p class="mb-0 small"><strong>Tramo IDs Waitry:</strong> <span id="meta-ids"></span></p>
            </div>
        </div>

        <div id="panel-proceso-notas" class="d-none">
            <ul class="text-muted small mb-2" id="lista-notas"></ul>
        </div>

        <div id="panel-proceso-grilla" class="d-none">
            <div class="table-responsive mb-2">
                <table class="table table-bordered table-sm mb-0" id="tabla-cuadro-cierre">
                    <thead class="thead-light">
                        <tr>
                            <th>Concepto</th>
                            <th class="text-right">QR</th>
                            <th class="text-right">MP</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Otros</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cuadro-cierre"></tbody>
                    <tfoot class="font-weight-bold">
                        <tr>
                            <td>Total cuadro (Anita + Waitry pend./impago)</td>
                            <td class="text-right" id="cuadro-total-qr"></td>
                            <td class="text-right" id="cuadro-total-mp"></td>
                            <td class="text-right" id="cuadro-total-efectivo"></td>
                            <td class="text-right" id="cuadro-total-otros"></td>
                            <td class="text-right" id="cuadro-total-general"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="small text-muted mb-2">
                Base del <strong>%</strong>: total neto facturado Anita (facturas − NC, fechajornada)
                (<span id="label-total-facturacion"></span>).
                Pendiente a facturar: <span id="label-pendiente-facturar"></span>.
                Impago Waitry (ref.): <span id="label-impago-waitry"></span>.
                <br>Haga clic en un importe del cuadro para ver el detalle de comandas con fecha/hora (conciliar vs Waitry).
            </p>
            <div class="form-inline mb-2 flex-wrap">
                <label for="input-porcentaje" class="mr-2 small">Porcentaje</label>
                <input type="number" id="input-porcentaje" class="form-control form-control-sm mr-1" style="width:90px"
                       min="0" max="100" step="0.01" value="0">
                <span class="mr-2 small">%</span>
                <button type="button" class="btn btn-sm btn-primary mr-2 mb-1" id="btn-proceso-recalcular">
                    Recalcular medios
                </button>
                <button type="button" class="btn btn-sm btn-outline-info mr-2 mb-1" id="btn-proceso-preview-asientos" disabled>
                    <i class="fa fa-file-text-o"></i> Preview asientos factura
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-1" id="btn-proceso-comandas-factura" disabled>
                    <i class="fa fa-list"></i> Comandas incluidas
                </button>
                <span class="text-muted small mb-1" id="label-objetivo-importe"></span>
            </div>
        </div>

        <div id="panel-proceso-grupos" class="d-none">
            <h6 class="mt-2">Grupos (auditoría — detalle paginado)</h6>
            <p class="text-muted small mb-1">Desglose por categoría interna; no es la grilla de la factura del proceso.</p>
            <div id="acordeon-grupos"></div>
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
                        <p class="text-muted small mb-2">
                            Las cuentas se consultan filtradas por la empresa del formulario principal.
                        </p>
                        <table class="table table-sm table-bordered mb-0" id="tabla-config-cuentas">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:35%">Concepto</th>
                                    <th>Cuenta contable</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-config-cuentas">
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_ventas_id',
                                    'label' => 'Cuenta ventas',
                                ])
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_iva_id',
                                    'label' => 'Cuenta IVA (débito fiscal)',
                                ])
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_impuesto_interno_id',
                                    'label' => 'Cuenta impuesto interno',
                                ])
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_fondo_fijo_maquinas_id',
                                    'label' => 'Fondo fijo máquinas (ref.)',
                                ])
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('includes.contable.modalconsultacuentacontable')

    <div class="modal fade" id="modal-cuadro-detalle" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modal-cuadro-detalle-titulo">Detalle cuadro</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <p class="small text-muted mb-2" id="modal-cuadro-detalle-resumen"></p>
                    <div id="modal-cuadro-detalle-loading" class="d-none text-muted small">
                        <i class="fa fa-spinner fa-spin"></i> Cargando…
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora</th>
                                    <th class="text-right">Total</th>
                                    <th>Medio Waitry</th>
                                    <th>Factura Anita</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-cuadro-detalle"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-muted" id="modal-cuadro-detalle-pag"></span>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-secondary d-none" id="btn-cuadro-detalle-ant">Ant.</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary d-none" id="btn-cuadro-detalle-sig">Sig.</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-preview-asientos-factura" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Preview asientos — factura del proceso</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <div id="preview-factura-loading" class="d-none text-muted small mb-2">
                        <i class="fa fa-spinner fa-spin"></i> Calculando preview…
                    </div>
                    <div id="preview-factura-resumen" class="small mb-2"></div>
                    <div id="preview-factura-advertencias" class="mb-2"></div>
                    <p class="small mb-1">
                        <strong>Debe:</strong> <span id="preview-factura-debe"></span>
                        · <strong>Haber:</strong> <span id="preview-factura-haber"></span>
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Cuenta</th>
                                    <th>Concepto</th>
                                    <th class="text-right">Debe</th>
                                    <th class="text-right">Haber</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-preview-asientos-factura"></tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        Sin grabar. La emisión de la factura y el asiento contable se implementarán en un paso posterior.
                    </p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-preview-abrir-comandas">
                        <i class="fa fa-list"></i> Ver comandas incluidas
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-comandas-factura" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Comandas incluidas en la factura del proceso</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <p class="small text-muted mb-2" id="modal-comandas-factura-resumen"></p>
                    <div id="modal-comandas-factura-loading" class="d-none text-muted small mb-2">
                        <i class="fa fa-spinner fa-spin"></i> Cargando…
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora</th>
                                    <th class="text-right">Total</th>
                                    <th>Medio Waitry</th>
                                    <th>Medio planificado</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-comandas-factura"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small text-muted" id="modal-comandas-factura-pag"></span>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-secondary d-none" id="btn-comandas-factura-ant">Ant.</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary d-none" id="btn-comandas-factura-sig">Sig.</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
