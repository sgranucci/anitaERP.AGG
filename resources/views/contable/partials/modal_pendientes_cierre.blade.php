@php
    /** @var string $rutaListado */
    /** @var string $estadoPendiente */
    /** @var string $permisoEjecutar */
    /** @var string $textoIntro */
    /** @var bool $mostrarPuntoventa */
    /** @var bool $mostrarFacturado */
    /** @var string $labelTurnos */
    /** @var string $labelCobrado */
    /** @var bool $exigeCorrelatividad */
    $empFiltro = (int) ($filtros['empresa_id'] ?? 0);
    $empDefault = $empFiltro > 0
        ? $empFiltro
        : (int) (($empresa_query ?? collect())->first()->id ?? 0);
    $mostrarPuntoventa = $mostrarPuntoventa ?? true;
    $mostrarFacturado = $mostrarFacturado ?? true;
    $labelTurnos = $labelTurnos ?? 'Turnos';
    $labelCobrado = $labelCobrado ?? 'Total cobrado';
    $exigeCorrelatividad = ! empty($exigeCorrelatividad);
@endphp
<div class="modal fade" id="modal-pendientes-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content" style="max-height:calc(100vh - 3.5rem);">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fa fa-hourglass-half"></i> Pendientes de cierre contable
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="overflow-y:auto;">
                <p class="text-muted small mb-3">{!! $textoIntro !!}</p>
                @if ($exigeCorrelatividad)
                    <div class="alert alert-warning py-2 small">
                        <i class="fa fa-link"></i>
                        <strong>Correlatividad obligatoria:</strong> el cierre debe empezar por la jornada
                        pendiente m&aacute;s antigua. No se puede saltear fechas (FBI y acumulaci&oacute;n mensual).
                    </div>
                @endif
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6 mb-2">
                        <label for="pendientes-empresa-id">Empresa</label>
                        <select id="pendientes-empresa-id" class="form-control">
                            <option value="">— Seleccione —</option>
                            @foreach ($empresa_query as $emp)
                                <option value="{{ $emp->id }}" @selected($empDefault === (int) $emp->id)>
                                    {{ $emp->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6 mb-2 text-md-right">
                        <button type="button" class="btn btn-outline-info" id="btn-pendientes-actualizar" title="Recargar pendientes">
                            <i class="fa fa-refresh"></i> Actualizar
                        </button>
                        <span class="text-muted small ml-2 d-inline-block" id="pendientes-actualizado-en"></span>
                    </div>
                </div>

                <div id="pendientes-loading" class="text-center text-muted py-4 d-none">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <div class="mt-2">Consultando pendientes…</div>
                </div>
                <div id="pendientes-error-box" class="alert alert-danger d-none"></div>

                <div id="pendientes-contenido" class="d-none">
                    <div class="row text-center mb-3" id="pendientes-kpis">
                        <div class="col-6 col-md-3 mb-2">
                            <div class="border rounded py-2 px-1 h-100 bg-light">
                                <div class="text-muted small text-uppercase">Asientos a generar</div>
                                <div class="h4 mb-0 font-weight-bold" id="pendientes-kpi-grupos">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="border rounded py-2 px-1 h-100 bg-light">
                                <div class="text-muted small text-uppercase">{{ $labelTurnos }}</div>
                                <div class="h4 mb-0 font-weight-bold" id="pendientes-kpi-turnos">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="border rounded py-2 px-1 h-100 bg-light">
                                <div class="text-muted small text-uppercase">Jornadas</div>
                                <div class="h4 mb-0 font-weight-bold" id="pendientes-kpi-jornadas">0</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3 mb-2">
                            <div class="border rounded py-2 px-1 h-100 bg-light">
                                <div class="text-muted small text-uppercase">{{ $labelCobrado }}</div>
                                <div class="h4 mb-0 font-weight-bold" id="pendientes-kpi-cobrado">0,00</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-secondary py-2 mb-3" id="pendientes-rango-box">
                        <i class="fa fa-calendar"></i>
                        <span id="pendientes-rango-texto">Sin pendientes.</span>
                    </div>

                    <div id="pendientes-rango-cierre-box" class="border rounded p-3 mb-3 bg-white d-none">
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                            <p class="small font-weight-bold mb-0">
                                <i class="fa fa-lock text-success"></i>
                                Rango a cerrar
                            </p>
                            <button type="button" class="btn btn-link btn-sm p-0" id="btn-pendientes-rango-completo"
                                    title="Volver al rango completo de pendientes">
                                Usar todo lo pendiente
                            </button>
                        </div>
                        <p class="text-muted small mb-2">
                            Confirm&aacute; las jornadas a incluir. Lo que quede fuera del rango no se cierra.
                            @if ($exigeCorrelatividad)
                                El <strong>desde</strong> debe ser la jornada pendiente m&aacute;s antigua.
                            @endif
                        </p>
                        <div class="form-row">
                            <div class="form-group col-md-6 mb-2 mb-md-0">
                                <label for="pendientes-fecha-desde" class="small mb-1">Jornada desde</label>
                                <input type="date" id="pendientes-fecha-desde" class="form-control"
                                       @if ($exigeCorrelatividad) readonly @endif>
                            </div>
                            <div class="form-group col-md-6 mb-0">
                                <label for="pendientes-fecha-hasta" class="small mb-1">Jornada hasta</label>
                                <input type="date" id="pendientes-fecha-hasta" class="form-control">
                            </div>
                        </div>
                        <p class="small text-muted mb-0 mt-2" id="pendientes-rango-cierre-ayuda"></p>
                        <div id="pendientes-correl-aviso" class="alert alert-danger py-2 small mt-2 mb-0 d-none"></div>
                    </div>

                    <div id="pendientes-vacio" class="alert alert-success d-none mb-0">
                        <i class="fa fa-check-circle"></i>
                        No hay pendientes de cierre contable para esta empresa.
                    </div>
                    <div id="pendientes-vacio-rango" class="alert alert-warning d-none mb-0">
                        <i class="fa fa-exclamation-triangle"></i>
                        Hay pendientes, pero ninguno cae en el rango de fechas indicado.
                    </div>

                    <div id="pendientes-tablas" class="d-none">
                        <p class="small font-weight-bold mb-1">Detalle del rango seleccionado</p>
                        <div class="table-responsive mb-3" style="max-height:340px;overflow-y:auto;">
                            <table class="table table-sm table-bordered table-hover mb-0" id="tabla-pendientes-grupos">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Jornada</th>
                                        @if ($mostrarPuntoventa)
                                            <th>Punto de venta</th>
                                        @endif
                                        <th class="text-center">{{ $labelTurnos }}</th>
                                        <th class="text-right">{{ $labelCobrado }}</th>
                                        @if ($mostrarFacturado)
                                            <th class="text-right">Facturado</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody id="pendientes-grupos-tbody"></tbody>
                            </table>
                        </div>

                        <div id="pendientes-por-dia-box" class="d-none">
                            <p class="small font-weight-bold mb-1">Resumen por jornada</p>
                            <div class="table-responsive" style="max-height:220px;overflow-y:auto;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Jornada</th>
                                            <th class="text-center">{{ $labelTurnos }}</th>
                                            <th class="text-center">Asientos</th>
                                            <th class="text-right">{{ $labelCobrado }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pendientes-por-dia-tbody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top justify-content-between">
                <div>
                    <a href="#" class="btn btn-outline-secondary d-none" id="btn-pendientes-ver-listado"
                       title="Abrir el listado filtrado por pendientes">
                        <i class="fa fa-list"></i> Ver en listado
                    </a>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    @if (can($permisoEjecutar, false))
                        <button type="button" class="btn btn-success d-none" id="btn-pendientes-cerrar-rango"
                                title="Abrir cierre por rango con estas fechas">
                            <i class="fa fa-calendar-check-o"></i> Cerrar este rango
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="pendientes-url-listado-base"
       value="{{ $rutaListado }}"
       data-estado-pendiente="{{ $estadoPendiente }}"
       data-mostrar-puntoventa="{{ $mostrarPuntoventa ? '1' : '0' }}"
       data-mostrar-facturado="{{ $mostrarFacturado ? '1' : '0' }}"
       data-exige-correlatividad="{{ $exigeCorrelatividad ? '1' : '0' }}"
       data-label-monto="{{ $labelCobrado }}">
