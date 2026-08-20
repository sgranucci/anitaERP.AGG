@if ($puede_proceso_cierre ?? false)
    <style>
        #tabla-cuadro-cierre td.cuadro-celda-detalle {
            cursor: pointer;
            text-decoration: underline;
            text-decoration-style: dotted;
            text-underline-offset: 2px;
        }
        #tabla-cuadro-cierre td.cuadro-celda-detalle:hover {
            text-decoration-style: solid;
            background-color: rgba(0, 123, 255, 0.08);
        }
        #tabla-cuadro-cierre th.cuadro-col-medio,
        #tabla-cuadro-cierre td.cuadro-col-medio {
            min-width: 72px;
            max-width: 120px;
            font-size: 0.8125rem;
            white-space: nowrap;
        }
        #tabla-cuadro-cierre th.cuadro-col-medio {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #modal-preview-asientos-factura .waitry-asiento-col-monto,
        #modal-preview-asientos-factura th.waitry-asiento-col-monto,
        #modal-preview-asientos-factura td.waitry-asiento-col-monto {
            min-width: 7.25rem;
            white-space: nowrap;
        }
        .waitry-cierre-col-monto,
        th.waitry-cierre-col-monto,
        td.waitry-cierre-col-monto {
            min-width: 6.85rem;
            max-width: 10rem;
            white-space: nowrap;
            text-align: right !important;
            font-variant-numeric: tabular-nums;
            font-feature-settings: "tnum";
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #tabla-cuadro-cierre td.cuadro-col-medio,
        #tabla-cuadro-cierre th.cuadro-col-medio {
            min-width: 6.85rem;
            max-width: 10rem;
        }
        #emitir-proceso-lotes-tabla td.waitry-cierre-col-ids,
        #emitir-proceso-lotes-tabla th.waitry-cierre-col-ids {
            max-width: 14rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.8125rem;
        }
        #tabla-comandas-factura td,
        #tabla-cuadro-detalle td,
        #emitir-proceso-lotes-tabla td {
            vertical-align: middle;
        }
        #modal-preview-asientos-factura .card-header .btn-link {
            white-space: normal;
        }
        #modal-cuadro-detalle .modal-body,
        #modal-comandas-factura .modal-body {
            max-height: min(88vh, 720px);
            overflow-y: auto;
        }
        .waitry-cierre-modal-tabla-wrap {
            overflow: visible;
        }
        .waitry-cierre-modal-tabla-wrap.waitry-cierre-dt-activo {
            overflow: visible;
            max-height: none;
        }
        .waitry-cierre-modal-tabla-wrap .dataTables_wrapper {
            width: 100%;
        }
        .waitry-cierre-modal-tabla-wrap .dt-buttons {
            margin-bottom: 0.5rem;
        }
        .waitry-cierre-modal-tabla-wrap .dt-buttons .btn-app {
            margin: 2px;
        }
        #proceso-recalculo-banner-overlay {
            position: fixed;
            inset: 0;
            z-index: 1085;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            background: rgba(0, 0, 0, 0.4);
        }
        #proceso-recalculo-banner-overlay.is-visible {
            display: flex;
        }
        #proceso-recalculo-banner {
            max-width: 42rem;
            width: 100%;
            margin: 0;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.25);
        }
        body.waitry-recalculo-banner-abierto {
            overflow: hidden;
        }
        body.waitry-recalculo-en-curso {
            overflow: hidden;
        }
        body.waitry-proceso-en-curso {
            overflow: hidden;
        }
        body.waitry-aviso-vivo-activo {
            overflow: hidden;
        }
        #proceso-aviso-vivo-overlay {
            position: fixed;
            inset: 0;
            z-index: 2060;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.55);
        }
        #proceso-aviso-vivo-overlay.is-visible {
            display: flex;
        }
        #proceso-aviso-vivo-card {
            max-width: 34rem;
            width: 100%;
            min-width: 18rem;
        }
        #proceso-aviso-vivo-paso {
            min-height: 1.35rem;
        }
    </style>
    <hr class="my-4">
    <h4 class="mb-2">
        <i class="fa fa-calculator"></i> Proceso de cierre — redistribución, asientos y facturación
    </h4>
    @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
        'id' => 'waitry-ayuda-proceso-intro',
        'label' => 'Ayuda — proceso de cierre',
        'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.proceso_intro',
    ])

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
            <button type="button" class="btn btn-outline-danger btn-sm" id="btn-proceso-ejecutar-automatico"
                    title="Ejecuta analizar + recalcular % + facturas + asientos para la jornada consultada (envía mail)">
                <i class="fa fa-bolt"></i> Probar cierre automático
            </button>
        </div>

        <div id="proceso-aviso-vivo-overlay"
             class="d-none"
             role="status"
             aria-live="assertive"
             aria-hidden="true">
            <div id="proceso-aviso-vivo-card" class="bg-white rounded shadow text-center px-4 py-3">
                <i id="proceso-aviso-vivo-icon"
                   class="fa fa-spinner fa-spin fa-2x text-info mb-2"
                   aria-hidden="true"></i>
                <div><strong id="proceso-aviso-vivo-titulo">Procesando…</strong></div>
                <div id="proceso-aviso-vivo-paso" class="small font-weight-bold text-primary mt-2"></div>
                <div class="small text-muted mt-1" id="proceso-aviso-vivo-subtitulo"></div>
                <div class="small text-muted mt-2">Por favor espere. No cierre ni recargue la página.</div>
            </div>
        </div>
        <div id="proceso-loading" class="alert alert-info d-none py-2" aria-hidden="true">
            <i class="fa fa-spinner fa-spin"></i> Procesando movimientos Waitry…
        </div>
        <div id="proceso-error" class="alert alert-danger d-none py-2"></div>
        <div id="proceso-recalculo-ok" class="d-none" aria-hidden="true"></div>
        <div id="proceso-recalculando-overlay"
             class="d-none"
             role="status"
             aria-live="assertive"
             aria-hidden="true"
             style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; align-items: center; justify-content: center; padding: 1rem;">
            <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 18rem;">
                <i class="fa fa-spinner fa-spin fa-2x text-primary mb-2" aria-hidden="true"></i>
                <div><strong>Recalculando medios de pago…</strong></div>
                <div id="proceso-recalculando-paso" class="small font-weight-bold text-primary mt-2"></div>
                <div class="small text-muted mt-1">
                    Aplicando el porcentaje <strong id="proceso-recalculando-porcentaje">—</strong> %
                    sobre el facturado Anita.
                </div>
                <div class="small text-muted mt-2">Por favor espere. No cierre ni recargue la página.</div>
            </div>
        </div>
        <div id="proceso-emitiendo-facturas-overlay"
             class="d-none"
             role="status"
             aria-live="assertive"
             aria-hidden="true"
             style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; align-items: center; justify-content: center; padding: 1rem;">
            <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 20rem;">
                <i class="fa fa-spinner fa-spin fa-2x text-success mb-2" aria-hidden="true"></i>
                <div><strong id="proceso-emitiendo-facturas-titulo">Generando facturas del proceso…</strong></div>
                <div class="small text-muted mt-1" id="proceso-emitiendo-facturas-subtitulo">
                    Emitiendo comprobantes CF por lotes según el cuadre.
                </div>
                <div class="small text-muted mt-2">Por favor espere. No cierre ni recargue la página.</div>
            </div>
        </div>
        <div id="proceso-grabando-asientos-overlay"
             class="d-none"
             role="status"
             aria-live="assertive"
             aria-hidden="true"
             style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; align-items: center; justify-content: center; padding: 1rem;">
            <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 20rem;">
                <i class="fa fa-spinner fa-spin fa-2x text-primary mb-2" aria-hidden="true"></i>
                <div><strong id="proceso-grabando-asientos-titulo">Grabando asientos contables…</strong></div>
                <div class="small text-muted mt-1" id="proceso-grabando-asientos-subtitulo">
                    Persistiendo el preview validado en ERP y Anita ctamov.
                </div>
                <div class="small text-muted mt-2">Por favor espere. No cierre ni recargue la página.</div>
            </div>
        </div>
        <div id="proceso-revirtiendo-overlay"
             class="d-none"
             role="status"
             aria-live="assertive"
             aria-hidden="true"
             style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; align-items: center; justify-content: center; padding: 1rem;">
            <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 20rem;">
                <i class="fa fa-spinner fa-spin fa-2x text-danger mb-2" aria-hidden="true"></i>
                <div><strong id="proceso-revirtiendo-titulo">Revirtiendo proceso de cierre…</strong></div>
                <div class="small text-muted mt-1" id="proceso-revirtiendo-subtitulo">
                    Eliminando asientos, facturas y ajustes en ERP y Anita.
                </div>
                <div class="small text-muted mt-2">Por favor espere. No cierre ni recargue la página.</div>
            </div>
        </div>
        <div id="proceso-recalculo-banner-overlay" class="d-none" role="dialog" aria-modal="true"
             aria-labelledby="proceso-recalculo-banner-titulo" aria-hidden="true">
            <div id="proceso-recalculo-banner" class="alert alert-success mb-0 py-3 px-4" role="status" aria-live="polite">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="alert-heading mb-0" id="proceso-recalculo-banner-titulo">
                        <i class="fa fa-check-circle"></i> Medios recalculados
                    </h5>
                    <button type="button" class="close ml-3" id="btn-proceso-recalculo-banner-cerrar"
                            aria-label="Cerrar mensaje de recálculo">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div id="proceso-recalculo-banner-body" class="small mb-0"></div>
                <hr class="my-2">
                <button type="button" class="btn btn-success btn-sm" id="btn-proceso-recalculo-banner-entendido">
                    Entendido, revisar cuadro
                </button>
            </div>
        </div>

        <div id="panel-proceso-meta" class="d-none mb-2">
            <div id="alert-jornada-proceso" class="alert py-2 mb-2 d-none"></div>
            <div id="alert-snapshot-congelado" class="alert alert-info py-2 mb-2 d-none">
                <i class="fa fa-lock"></i>
                <span id="texto-snapshot-congelado"></span>
            </div>
            <div class="alert alert-secondary py-2 mb-0">
                <p class="mb-1 small"><strong>Ventana operativa:</strong> <span id="meta-ventana"></span></p>
                <p class="mb-1 small"><strong>Rango calendario Waitry (API):</strong> <span id="meta-rango"></span></p>
                <p class="mb-1 small"><strong>Órdenes incluidas:</strong> <span id="meta-cantidad"></span></p>
                <p class="mb-1 small d-none" id="meta-canceladas-wrap">
                    <strong>Waitry canceladas (excluidas del cuadro):</strong>
                    <span id="meta-canceladas"></span>
                </p>
                <p class="mb-1 small d-none" id="meta-anuladas-descuento-wrap">
                    <strong>Waitry anuladas por descuento 100 % (excluidas del cuadro):</strong>
                    <span id="meta-anuladas-descuento"></span>
                </p>
                <p class="mb-1 small"><strong>Último ticket (origen):</strong> <span id="meta-ultimo-ticket-origen"></span></p>
                <p class="mb-0 small"><strong>Tramo IDs Waitry:</strong> <span id="meta-ids"></span></p>
            </div>
        </div>

        <div id="panel-proceso-notas" class="d-none">
            @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                'id' => 'waitry-ayuda-proceso-notas',
                'label' => 'Ayuda — notas del análisis',
                'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.proceso_notas_lista',
            ])
        </div>

        <div id="panel-proceso-anita-sin-waitry" class="d-none mb-3">
            <div class="card border-info">
                <div class="card-header py-2 bg-info text-white d-flex flex-wrap align-items-center justify-content-between">
                    <h6 class="mb-0">
                        <i class="fa fa-desktop"></i>
                        <span id="anita-sin-waitry-titulo">Facturas POS — terminales sin integración Waitry</span>
                    </h6>
                    <span class="small" id="anita-sin-waitry-resumen"></span>
                </div>
                <div class="card-body py-2">
                    <p class="small text-muted mb-2" id="anita-sin-waitry-ayuda">
                        Emisiones del día desde PCs con Waitry deshabilitado en configuración PV gastronomía.
                        Entran en el asiento 2 (facturación Anita jornada). No provienen del tramo Waitry.
                    </p>
                    <button type="button" class="btn btn-sm btn-outline-info mb-2 collapsed" id="btn-anita-sin-waitry-detalle"
                            data-toggle="collapse" data-target="#anita-sin-waitry-detalle" aria-expanded="false">
                        <i class="fa fa-list"></i> Ver detalle para auditoría
                    </button>
                    <div id="anita-sin-waitry-detalle" class="collapse">
                        <div id="anita-sin-waitry-detalle-body" class="grupo-detalle" data-grupo="anita_sin_waitry">
                            <p class="text-muted small mb-0">Expandir para cargar el listado…</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-proceso-grilla" class="d-none">
            <div class="table-responsive mb-2">
                <table class="table table-bordered table-sm mb-0" id="tabla-cuadro-cierre">
                    <thead class="thead-light">
                        <tr id="thead-cuadro-cierre">
                            <th>Concepto</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-cuadro-cierre"></tbody>
                    <tfoot class="font-weight-bold">
                        <tr id="tfoot-cuadro-cierre">
                            <td>Total cuadro (Anita + Waitry pend./impago)</td>
                            <td class="text-right" id="cuadro-total-general"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                'id' => 'waitry-ayuda-proceso-cuadro',
                'label' => 'Ayuda — cuadro y porcentaje',
                'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.proceso_cuadro',
            ])
            <div id="proceso-error-acciones" class="alert alert-danger d-none py-2 mb-2" role="alert"
                 aria-live="polite"></div>
            <div class="form-inline mb-2 flex-wrap">
                <label for="input-porcentaje" class="mr-2 small">Porcentaje</label>
                <input type="number" id="input-porcentaje" class="form-control form-control-sm mr-1" style="width:90px"
                       min="0" max="100" step="0.0001"
                       value="{{ number_format((float) ($porcentaje_proceso_config ?? 0), 4, '.', '') }}"
                       title="Objetivo sobre facturado Anita (default 25%). Si el disponible a recodificar es menor, se aplica ese tope (3er asiento). El automático usa la misma regla.">
                <span class="mr-2 small">%</span>
                <button type="button" class="btn btn-sm btn-primary mr-2 mb-1" id="btn-proceso-recalcular">
                    Recalcular medios
                </button>
                <button type="button" class="btn btn-sm btn-outline-info mr-2 mb-1" id="btn-proceso-preview-asientos" disabled>
                    <i class="fa fa-file-text-o"></i> Preview asientos del proceso
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-1" id="btn-proceso-comandas-factura" disabled>
                    <i class="fa fa-list"></i> Comandas incluidas
                </button>
                <button type="button" class="btn btn-sm btn-success mb-1" id="btn-proceso-emitir-factura" disabled
                        title="Solo tras cerrar la jornada en Gastronomía y analizar el tramo definitivo">
                    <i class="fa fa-file-invoice"></i> Emitir facturas del proceso
                </button>
                <button type="button" class="btn btn-sm btn-primary mb-1" id="btn-proceso-grabar-asientos" disabled
                        title="Tras emitir la factura: persiste los asientos del preview en contabilidad (ctamov)">
                    <i class="fa fa-book"></i> Grabar asientos contables
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger mb-1 d-none" id="btn-proceso-revertir"
                        title="Elimina facturas, asientos y ajuste de insumos del proceso para volver a emitir">
                    <i class="fa fa-undo"></i> Revertir proceso
                </button>
                <span class="text-muted small mb-1" id="label-objetivo-importe"></span>
            </div>
            <div class="w-100 small mb-2" id="panel-contexto-porcentaje">
                <span class="text-muted">
                    Facturado Anita (base del %):
                    <strong id="ctx-facturacion-anita">—</strong>
                    · Disponible a recodificar (QR/Totalcoin + MP → 3er asiento):
                    <strong id="ctx-sin-facturar-recodificable">—</strong>
                    · Objetivo:
                    <strong id="ctx-porcentaje-objetivo">—</strong>
                    · Tope del día:
                    <strong id="ctx-porcentaje-maximo">—</strong>
                    · Se aplica:
                    <strong id="ctx-porcentaje-aplicar">—</strong>
                </span>
                <span class="d-none text-danger d-block mt-1" id="ctx-porcentaje-excedido"></span>
                <br>
                <span class="text-muted" id="ctx-objetivo-tras-pct-wrap">
                    Al % indicado → objetivo recodificar:
                    <strong id="ctx-objetivo-importe">—</strong>
                    · Pendiente QR/MP a facturar (tras recodificar):
                    <strong id="ctx-pendiente-qr-mp-tras">—</strong>
                </span>
            </div>
            <div id="panel-proceso-resultado" class="d-none mb-3">
                <div class="card border-success">
                    <div class="card-header py-2 bg-light d-flex flex-wrap align-items-center justify-content-between">
                        <h6 class="mb-0">
                            <i class="fa fa-check-circle text-success"></i>
                            Resultado del proceso — facturas y asientos
                        </h6>
                        <div class="d-flex flex-wrap align-items-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary mr-2 mb-1 d-none"
                                    id="btn-proceso-imprimir-pdfs-facturas"
                                    title="Abrir los PDF de las facturas emitidas (opcional)">
                                <i class="fa fa-print"></i> Imprimir PDFs
                            </button>
                            <span class="badge badge-success d-none mb-1" id="badge-proceso-cierre-completado">Cierre completado</span>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <p class="small text-muted mb-2" id="proceso-resultado-resumen"></p>
                        <h6 class="small font-weight-bold mb-1">Facturas emitidas</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0" id="tabla-proceso-resultado-facturas">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Lote</th>
                                        <th>Comprobante</th>
                                        <th class="text-right waitry-cierre-col-monto">Total</th>
                                        <th class="text-right">Comandas</th>
                                        <th class="text-nowrap">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-proceso-resultado-facturas"></tbody>
                                <tfoot class="font-weight-bold">
                                    <tr id="tfoot-proceso-resultado-facturas-total" class="d-none">
                                        <td colspan="2">Total facturación proceso</td>
                                        <td class="text-right waitry-cierre-col-monto" id="proceso-resultado-total-factura"></td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div id="proceso-resultado-ajuste-wrap" class="d-none mb-3">
                            <h6 class="small font-weight-bold mb-1">Ajuste de insumos (comandas efectivo)</h6>
                            <p class="small mb-0" id="proceso-resultado-ajuste"></p>
                        </div>
                        <h6 class="small font-weight-bold mb-1">Asientos contables grabados</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0" id="tabla-proceso-resultado-asientos">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Asiento</th>
                                        <th>Nº</th>
                                        <th class="text-right waitry-cierre-col-monto">Debe</th>
                                        <th class="text-right waitry-cierre-col-monto">Haber</th>
                                        <th class="text-nowrap">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-proceso-resultado-asientos"></tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-0 mt-2 d-none" id="proceso-resultado-asientos-pendientes">
                            <i class="fa fa-info-circle"></i> Facturas emitidas; pendiente grabar asientos contables.
                        </p>
                    </div>
                </div>
            </div>
            <div id="panel-proceso-redistribucion" class="d-none mb-3">
                <div class="alert alert-light border py-2 mb-2" id="alert-redistribucion-resumen"></div>
                @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                    'id' => 'waitry-ayuda-proceso-redistribucion',
                    'label' => 'Ayuda — redistribución QR ↔ efectivo',
                    'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.proceso_redistribucion',
                ])
                <h6 class="mb-1">Waitry sin facturar — QR → efectivo (planificado)</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0" id="tabla-redistribucion-waitry">
                        <thead class="thead-light">
                            <tr>
                                <th>#Waitry</th>
                                <th>Ref.</th>
                                <th class="text-right">Total</th>
                                <th>Medio planificado</th>
                                <th>Ajuste</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-redistribucion-waitry"></tbody>
                    </table>
                </div>
                <h6 class="mb-1">Facturas Anita — efectivo → medio original (compensación, planificado)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0" id="tabla-redistribucion-anita">
                        <thead class="thead-light">
                            <tr>
                                <th>Factura</th>
                                <th>#Waitry</th>
                                <th class="text-right">Total</th>
                                <th>Medio planificado</th>
                                <th>Ajuste</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-redistribucion-anita"></tbody>
                    </table>
                </div>
                <p class="text-muted small mb-0 mt-1" id="redistribucion-sin-ajustes"></p>
            </div>
        </div>

        <div id="panel-proceso-grupos" class="d-none">
            <h6 class="mt-2">Grupos (auditoría — detalle paginado)</h6>
            @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                'id' => 'waitry-ayuda-proceso-grupos',
                'label' => 'Ayuda — grupos de auditoría',
                'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.proceso_grupos',
            ])
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
                            El porcentaje se guarda por empresa (objetivo, default 25%) y lo usan el proceso manual y el automático.
                            En cada jornada se aplica el menor entre ese objetivo y el disponible recodificable (3er asiento).
                        </p>
                        <div class="form-group row mb-3">
                            <label for="config-porcentaje" class="col-sm-5 col-form-label col-form-label-sm">
                                Porcentaje proceso (%)
                            </label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control form-control-sm" id="config-porcentaje"
                                       name="porcentaje" min="0" max="100" step="0.0001"
                                       placeholder="25">
                            </div>
                        </div>
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
                                    'campoId' => 'cuenta_ventas_kiosco_id',
                                    'label' => 'Cuenta ventas de cigarrillos (414020001 tabaco)',
                                ])
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_fondo_fijo_maquinas_id',
                                    'label' => 'Fondo fijo máquinas (ref.)',
                                ])
                                @include('caja.waitry_cierre_jornada.partials.campo_cuentacontable_config', [
                                    'campoId' => 'cuenta_diferencia_caja_id',
                                    'label' => 'Diferencia de caja (invitaciones $0,01)',
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
                    <div class="waitry-cierre-modal-tabla-wrap">
                        <table class="table table-sm table-striped mb-0 w-100" id="tabla-cuadro-detalle">
                            <thead class="thead-light">
                                <tr>
                                    <th>#Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora</th>
                                    <th class="text-right waitry-cierre-col-monto">Total</th>
                                    <th>Medio Waitry</th>
                                    <th>Medio Anita / planif.</th>
                                    <th>Factura Anita</th>
                                    <th class="text-nowrap" data-orderable="false">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-cuadro-detalle"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-preview-asientos-factura" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Preview asientos — cierre Waitry</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <div id="preview-factura-loading" class="d-none text-muted small mb-2">
                        <i class="fa fa-spinner fa-spin"></i> Calculando preview…
                    </div>
                    <div id="preview-factura-resumen" class="small mb-2"></div>
                    <div id="preview-factura-advertencias" class="mb-2"></div>
                    <div id="preview-cuentas-requeridas-wrap" class="d-none mb-3">
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm py-0 px-2 collapsed mb-1"
                                data-toggle="collapse"
                                data-target="#preview-cuentas-requeridas-collapse"
                                aria-expanded="false"
                                aria-controls="preview-cuentas-requeridas-collapse">
                            <i class="fa fa-list-alt"></i> Cuentas requeridas
                        </button>
                        <div class="collapse" id="preview-cuentas-requeridas-collapse">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Concepto</th>
                                            <th>Código cuenta</th>
                                            <th>Nombre cuenta</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-preview-cuentas-requeridas"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div id="preview-cuadre-asientos-wrap" class="mb-3 d-none">
                        <h6 class="small font-weight-bold mb-1">
                            Cuadre de asientos
                            <span id="preview-cuadre-badge" class="badge badge-secondary ml-1"></span>
                        </h6>
                        <div class="table-responsive mb-2">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Asiento</th>
                                        <th class="text-right waitry-asiento-col-monto">Total</th>
                                        <th class="text-right waitry-asiento-col-monto">Debe</th>
                                        <th class="text-right waitry-asiento-col-monto">Haber</th>
                                        <th class="text-center">D=H</th>
                                        <th>Referencia</th>
                                        <th class="text-right waitry-asiento-col-monto">Ref.</th>
                                        <th class="text-right waitry-asiento-col-monto">Dif.</th>
                                        <th class="text-center">OK</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-preview-cuadre-asientos"></tbody>
                            </table>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Validación global</th>
                                        <th class="text-right waitry-asiento-col-monto">Asientos</th>
                                        <th class="text-right waitry-asiento-col-monto">Referencia</th>
                                        <th class="text-right waitry-asiento-col-monto">Dif.</th>
                                        <th class="text-center">OK</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-preview-cuadre-validaciones"></tbody>
                            </table>
                        </div>
                    </div>
                    <p class="small mb-2">
                        <strong>Total proceso — Debe:</strong> <span id="preview-factura-debe"></span>
                        · <strong>Haber:</strong> <span id="preview-factura-haber"></span>
                    </p>
                    <div id="preview-asientos-acordeon"></div>
                    @include('caja.waitry_cierre_jornada.partials.ayuda_colapsable', [
                        'id' => 'waitry-ayuda-preview-asientos',
                        'label' => 'Ayuda — asientos del proceso',
                        'inner' => 'caja.waitry_cierre_jornada.partials.ayuda.preview_asientos',
                        'wrapperClass' => 'mt-2 mb-0',
                    ])
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
                    <h5 class="modal-title" id="modal-comandas-factura-titulo">Comandas incluidas en la factura del proceso</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body p-2">
                    <p class="small text-muted mb-2" id="modal-comandas-factura-resumen"></p>
                    <div id="modal-comandas-factura-loading" class="d-none text-muted small mb-2">
                        <i class="fa fa-spinner fa-spin"></i> Cargando…
                    </div>
                    <div class="waitry-cierre-modal-tabla-wrap">
                        <table class="table table-sm table-striped mb-0 w-100" id="tabla-comandas-factura">
                            <thead class="thead-light">
                                <tr>
                                    <th>#Waitry</th>
                                    <th>Ref.</th>
                                    <th>Fecha/hora</th>
                                    <th class="text-right waitry-cierre-col-monto">Total</th>
                                    <th>Medio Waitry</th>
                                    <th>Medio Anita / planif.</th>
                                    <th>Factura Anita</th>
                                    <th class="text-nowrap" data-orderable="false">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-comandas-factura"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="modal-emitir-factura-proceso" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Emitir facturas del proceso</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Facturas CF en lotes de alrededor de
                    ${{ number_format(
                        \App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaLotesSupport::objetivoLote(
                            (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 0),
                            (float) config('gastronomia.cierre_jornada_cf_lote_porcentaje_tope', 20),
                            (float) config('gastronomia.cierre_jornada_cf_lote_monto', 0),
                        ),
                        0,
                        ',',
                        '.'
                    ) }}.
                    Comandas con QR/MP van completas a facturación; las 100 % efectivo
                    generan un único ajuste de insumos al final.
                </p>
                <div class="form-group">
                    <label for="emitir-proceso-puntoventa">Punto de venta</label>
                    <select id="emitir-proceso-puntoventa" class="form-control form-control-sm"></select>
                </div>
                <div class="form-group">
                    <label for="emitir-proceso-fecha-factura">Fecha de factura</label>
                    <input type="date" id="emitir-proceso-fecha-factura" class="form-control form-control-sm">
                    <small class="form-text text-muted">
                        Fecha fiscal (CbteFch). La jornada del cierre no se modifica.
                    </small>
                </div>
                <div id="emitir-proceso-caea-correlatividad" class="alert alert-warning d-none py-2 small" role="alert"></div>
                <div id="emitir-proceso-lotes-resumen" class="small text-muted mb-2 d-none"></div>
                <div class="table-responsive" id="emitir-proceso-lotes-wrap" style="max-height: 240px; overflow-y: auto;">
                    <table class="table table-sm table-bordered mb-0 d-none" id="emitir-proceso-lotes-tabla">
                        <thead class="thead-light">
                            <tr>
                                <th>Lote</th>
                                <th class="text-right">Comandas</th>
                                <th class="text-right waitry-cierre-col-monto">Total</th>
                                <th class="waitry-cierre-col-ids">Waitry IDs</th>
                            </tr>
                        </thead>
                        <tbody id="emitir-proceso-lotes-body"></tbody>
                    </table>
                </div>
                <div id="emitir-proceso-loading" class="d-none small text-muted mt-2">
                    <i class="fa fa-spinner fa-spin"></i> Cargando opciones…
                </div>
                <div id="emitir-proceso-error" class="alert alert-danger d-none py-2 mt-2 mb-0 small"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-confirmar-emitir-factura-proceso">
                    <i class="fa fa-file-invoice"></i> Emitir facturas
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-revertir-proceso-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-light">
                <h5 class="modal-title text-danger"><i class="fa fa-undo"></i> Revertir proceso de cierre</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3">
                    <strong>Atención:</strong> se eliminarán en ERP y Anita los comprobantes y asientos del proceso
                    de esta jornada. Podrá volver a emitir facturas y grabar asientos con el código corregido.
                </div>
                <div id="revertir-proceso-resumen-facturas-wrap" class="d-none mb-3">
                    <h6 class="small font-weight-bold mb-1">Facturas a eliminar</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Lote</th>
                                    <th>Comprobante</th>
                                    <th class="text-right waitry-cierre-col-monto">Total</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-revertir-proceso-facturas"></tbody>
                        </table>
                    </div>
                </div>
                <div id="revertir-proceso-resumen-asientos-wrap" class="d-none mb-3">
                    <h6 class="small font-weight-bold mb-1">Asientos a eliminar</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Asiento</th>
                                    <th>Nº</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-revertir-proceso-asientos"></tbody>
                        </table>
                    </div>
                </div>
                <div id="revertir-proceso-ajuste-wrap" class="d-none mb-0">
                    <h6 class="small font-weight-bold mb-1">Ajuste de insumos</h6>
                    <p class="small mb-0" id="revertir-proceso-ajuste-texto"></p>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirmar-revertir-proceso">
                    <i class="fa fa-undo"></i> Confirmar reversión
                </button>
            </div>
        </div>
    </div>
</div>
