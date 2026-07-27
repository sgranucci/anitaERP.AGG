@extends("theme.$theme.layout")
@section('titulo')
    Habilitación y cierres de turno gastronomía
@endsection

@section("scripts")
<style>
    .gastro-totales-panel { font-size: 1rem; }
    .gastro-totales-monto { font-size: 1.2rem; line-height: 1.3; }
    .gastro-totales-leyenda { font-size: 0.95rem !important; }
    .gastro-totales-tabla { font-size: 0.95rem; }
    .gastro-totales-tabla th,
    .gastro-totales-tabla td { padding: 0.45rem 0.6rem; vertical-align: middle; }
    .gastro-mozo-nombre { font-size: 1.05rem; }
    .gastro-turno-resumen-wrap { font-size: 1rem; }
    .gastro-turno-resumen-wrap strong { font-size: 1.05rem; }
    #panel-estado-turno .gastro-totales-bloque { box-shadow: none; }
    .gastro-grilla-conciliacion-wrap {
        max-height: 420px;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: 4px;
    }
    .gastro-grilla-conciliacion-wrap table { margin-bottom: 0; font-size: 0.85rem; white-space: nowrap; }
    .gastro-grilla-conciliacion-wrap th { position: sticky; top: 0; background: #f8f9fa; z-index: 2; }
    .gastro-medio-conciliar { cursor: pointer; text-decoration: underline dotted; }
    .gastro-tab-cierres {
        border-bottom: 2px solid #ced4da;
        background: #eef1f5;
        padding: 0.4rem 0.5rem 0;
        border-radius: 0.35rem 0.35rem 0 0;
        gap: 0.25rem;
    }
    .gastro-tab-cierres .nav-item { margin-bottom: -2px; }
    .gastro-tab-cierres .nav-link {
        font-weight: 600;
        font-size: 0.95rem;
        border: 2px solid transparent !important;
        border-bottom: none !important;
        border-radius: 0.35rem 0.35rem 0 0 !important;
        padding: 0.7rem 1.2rem;
        color: #6c757d;
        background: rgba(255, 255, 255, 0.45);
        transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .gastro-tab-cierres .nav-link:hover:not(.active) {
        color: #343a40;
        background: rgba(255, 255, 255, 0.85);
    }
    .gastro-tab-cierres .nav-link:not(.active) .fa { opacity: 0.55; }
    .gastro-tab-cierres .nav-link .fa { margin-right: 0.45rem; }
    .gastro-tab-cierres #tab-parcial-link.active {
        color: #664d03;
        background: #fff3cd;
        border-color: #ffc107 !important;
        border-bottom: 2px solid #fff3cd !important;
        box-shadow: 0 -3px 0 0 #ffc107 inset, 0 2px 6px rgba(255, 193, 7, 0.35);
    }
    .gastro-tab-cierres #tab-parcial-link.active .fa {
        color: #856404 !important;
        opacity: 1;
    }
    .gastro-tab-cierres #tab-definitivo-link.active {
        color: #58151c;
        background: #f8d7da;
        border-color: #dc3545 !important;
        border-bottom: 2px solid #f8d7da !important;
        box-shadow: 0 -3px 0 0 #dc3545 inset, 0 2px 6px rgba(220, 53, 69, 0.3);
    }
    .gastro-tab-cierres #tab-definitivo-link.active .fa {
        color: #842029 !important;
        opacity: 1;
    }
    #tabs-cierre-turno-content > .tab-pane.active.show {
        border-top: 4px solid #adb5bd;
        padding-top: 0.75rem;
        margin-top: -1px;
    }
    #tab-cierre-parcial.tab-pane.active.show { border-top-color: #ffc107; }
    #tab-cierre-definitivo.tab-pane.active.show { border-top-color: #dc3545; }
    .gastro-cierre-fila-efectivo { background: #f0f9ff !important; }
    .gastro-cierre-fila-efectivo td:first-child { border-left: 3px solid #17a2b8; }
    .gastro-rendicion-esperado-efectivo { color: #0c5460; }
    .gastro-campo-auto-actualizado {
        background-color: #fff3cd !important;
        transition: background-color 0.3s ease;
    }
</style>
<script>
    window.HABILITACION_TURNO_GASTRONOMIA = {
        csrf: @json(csrf_token()),
        modoCajaDirecto: @json($modo_caja_directo ?? false),
        accion: @json($accion ?? ''),
        urlFacturaVerBase: @json($url_factura_ver_base ?? url('ventas/gastronomia/facturas-dia')),
        puedeVerFactura: @json($puede_ver_factura ?? false),
        urlInformeMozoPdf: @json(route('gastronomia_habilitacion_turno_informe_mozo_pdf')),
        urlPdfParcialBase: @json(url('ventas/gastronomia/cierres-turno/parcial')),
        urlCanjesPremioTurno: @json(route('gastronomia_api_canjes_premio_turno')),
        urlTicketsTarjetaTurno: @json(route('gastronomia_api_tickets_tarjeta_turno')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/habilitacion_turno.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/habilitacion_turno.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="habilitacion-turno-app"
     data-api-estado="{{ url('ventas/gastronomia/habilitacion-turno/api/estado') }}"
     data-api-habilitar="{{ url('ventas/gastronomia/habilitacion-turno/api/habilitar') }}"
     data-api-actualizar-monto-habilitacion="{{ route('gastronomia_habilitacion_turno_api_actualizar_monto_habilitacion') }}"
     data-api-cierre-parcial="{{ url('ventas/gastronomia/habilitacion-turno/api/cierre-parcial') }}"
     data-api-cerrar="{{ url('ventas/gastronomia/habilitacion-turno/api/cerrar') }}"
     data-api-anular-cierre="{{ route('gastronomia_habilitacion_turno_api_anular_cierre') }}"
     data-api-conciliacion-turno="{{ url('ventas/gastronomia/habilitacion-turno/api/conciliacion-turno') }}"
     data-api-explicar-diferencias-conciliacion="{{ route('gastronomia_habilitacion_turno_api_explicar_diferencias') }}"
     data-api-conciliacion-medio="{{ url('ventas/gastronomia/habilitacion-turno/api/conciliacion-medio') }}"
     data-api-conciliacion-notas-credito="{{ url('ventas/gastronomia/habilitacion-turno/api/conciliacion-notas-credito') }}"
     data-api-conciliacion-invitaciones="{{ url('ventas/gastronomia/habilitacion-turno/api/conciliacion-invitaciones') }}"
     data-csrf="{{ csrf_token() }}"
     data-puede-habilitar="{{ ($puede_habilitar ?? false) ? '1' : '0' }}"
     data-puede-cierre-parcial="{{ ($puede_cierre_parcial ?? false) ? '1' : '0' }}"
     data-puede-cerrar="{{ ($puede_cerrar ?? false) ? '1' : '0' }}"
     data-puede-anular-cierre="{{ ($puede_anular_cierre ?? false) ? '1' : '0' }}"
     data-puede-modificar-monto-habilitacion="{{ ($puede_modificar_monto_habilitacion ?? false) ? '1' : '0' }}"
     data-puede-ver-factura="{{ ($puede_ver_factura ?? false) ? '1' : '0' }}"
     data-accion="{{ $accion ?? '' }}">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if ($modo_caja_directo ?? false)
            <div class="alert alert-info">
                El sistema está en modo <strong>caja directo</strong>
                (<code>GASTRONOMIA_REQUIERE_HABILITACION_TURNO=false</code>).
                No se utiliza habilitación ni cierre de turno por terminal.
            </div>
        @elseif (! $cfg)
            @include('ventas.gastronomia.habilitacion_turno.partials.filtro_empresa', [
                'empresa_query' => $empresa_query ?? collect(),
                'empresas_sin_pv' => $empresas_sin_pv ?? collect(),
                'empresa_id' => $empresa_id ?? 0,
                'identificador_pc' => $identificador_pc,
                'accion' => $accion ?? '',
            ])

            @if (($empresa_query ?? collect())->isNotEmpty())
                @php
                    $empresaNombreSeleccionada = ($empresa_query ?? collect())->firstWhere('id', (int) ($empresa_id ?? 0))?->nombre;
                @endphp
                <div class="alert alert-warning">
                    No hay configuración de punto de venta para el identificador PC
                    <code>{{ $identificador_pc }}</code>
                    @if ((int) ($empresa_id ?? 0) > 0 && $empresaNombreSeleccionada)
                        y la empresa <strong>{{ $empresaNombreSeleccionada }}</strong>.
                    @else
                        .
                    @endif
                </div>
            @endif
        @else
            @php
                $empresaNombreSeleccionada = ($empresa_query ?? collect())->firstWhere('id', (int) ($empresa_id ?? 0))?->nombre
                    ?? ($cfg->empresa->nombre ?? $cfg->empresa_id);
            @endphp
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">Habilitación y cierres de turno</h3>
                    <div class="card-tools d-flex flex-wrap align-items-center" style="gap: 4px;">
                        @if (can('gestionar-saneamiento-turno-gastronomia', false))
                            <a href="{{ route('gastronomia_saneamiento_turno', ['empresa_id' => $empresa_id ?? $cfg->empresa_id, 'identificador_pc' => $identificador_pc]) }}"
                               class="btn btn-light btn-sm border-dark text-dark" title="Diagnóstico y corrección de facturas huérfanas / cuentas">
                                <i class="fa fa-wrench"></i> Saneamiento turnos
                            </a>
                        @endif
                        <a href="{{ route('gastronomia_cierres_turno') }}" class="btn btn-light btn-sm border-dark text-dark">
                            <i class="fa fa-file-text-o"></i> Historial de cierres
                        </a>
                        <button type="button" class="btn btn-light btn-sm border-dark text-dark" id="btn-consultar-canjes-premio" title="Canjes de premios Wigos del turno">
                            <i class="fa fa-gift"></i> Canjes premio
                        </button>
                        <button type="button" class="btn btn-dark btn-sm" id="btn-consultar-tickets-tarjeta" title="Tickets tarjeta canjeados en el turno">
                            <i class="fa fa-barcode"></i> Tickets tarjeta
                        </button>
                        <button type="button" class="btn btn-warning btn-sm text-dark font-weight-bold" id="btn-consultar-invitaciones" title="Facturas de cortesía / invitación ($0,01 sin cobranza)">
                            <i class="fa fa-tag"></i> Invitaciones ($0,01)
                        </button>
                        @if ($puede_anular_cierre ?? false)
                            <button type="button" class="btn btn-danger btn-sm d-none" id="btn-abrir-anular-cierre"
                                    title="Anular el último cierre definitivo de esta terminal en la jornada activa">
                                <i class="fa fa-undo"></i> Anular último cierre
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @include('ventas.gastronomia.habilitacion_turno.partials.filtro_empresa', [
                        'empresa_query' => $empresa_query ?? collect(),
                        'empresas_sin_pv' => $empresas_sin_pv ?? collect(),
                        'empresa_id' => $empresa_id ?? 0,
                        'identificador_pc' => $identificador_pc,
                        'accion' => $accion ?? '',
                    ])

                    <p class="text-muted mb-3">
                        Terminal: <strong>{{ $identificador_pc }}</strong>
                        · Empresa: <strong>{{ $empresaNombreSeleccionada }}</strong>
                    </p>

                    @if (! empty($jornada['jornada_abierta']))
                        <div class="alert alert-info py-2 mb-3" id="alert-jornada-activa">
                            Jornada activa:
                            <strong>{{ $estado['fecha_jornada_fmt'] ?? $jornada['fecha_jornada_fmt'] ?? $jornada['fecha_jornada'] }}</strong>
                            @if (! empty($jornada['usuario_apertura']))
                                · Abierta por <strong>{{ $jornada['usuario_apertura'] }}</strong>
                                @if (! empty($jornada['apertura_en']))
                                    ({{ $jornada['apertura_en'] }})
                                @endif
                            @endif
                        </div>
                    @endif

                    @if (empty($jornada['jornada_abierta']))
                        <div class="alert alert-danger">
                            Debe abrir la <a href="{{ route('gastronomia_jornada', ['empresa_id' => $empresa_id ?? $cfg->empresa_id]) }}">jornada</a> antes de habilitar un turno.
                        </div>
                    @endif

                    <div id="panel-estado-turno" class="mb-3"></div>

                    @if ($puede_habilitar ?? false)
                        <div class="card card-outline card-success mb-3" id="card-habilitar">
                            <div class="card-header">Habilitar turno</div>
                            <div class="card-body">
                                <form id="form-habilitar-turno" autocomplete="off">
                                    @if (! empty($jornada['jornada_abierta']))
                                        <div class="form-group">
                                            <label>Jornada activa</label>
                                            <input type="text" class="form-control" id="fecha_jornada_activa" readonly
                                                   value="{{ $estado['fecha_jornada_fmt'] ?? $jornada['fecha_jornada_fmt'] ?? $jornada['fecha_jornada'] }}"/>
                                        </div>
                                    @endif
                                    <div class="form-group">
                                        <label for="turno_gastronomia_id" class="requerido">Turno</label>
                                        <select name="turno_gastronomia_id" id="turno_gastronomia_id" class="form-control" required>
                                            <option value="">Seleccione…</option>
                                            @foreach ($turnos as $t)
                                                <option value="{{ $t->id }}">{{ $t->nombre }} @if($t->etiquetaHorario() !== '—') ({{ $t->etiquetaHorario() }}) @endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="monto_habilitacion" class="requerido">Monto habilitación</label>
                                        <input type="number" step="0.01" min="0" name="monto_habilitacion" id="monto_habilitacion" class="form-control" required value="0"/>
                                    </div>
                                    <div class="form-group">
                                        <label for="usuario_habilitado_codigo" class="requerido">Usuario habilitado</label>
                                        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                                            <input type="hidden" name="usuario_habilitado_id" id="usuario_habilitado_id" class="usuario_id" value=""/>
                                            <input type="text" style="flex: 0 0 110px; width: 110px; height: 38px;" class="usuario_codigo_arbol form-control" id="usuario_habilitado_codigo" value="" placeholder="Código usuario" title="Código de login o ID numérico; Tab fuera para cargar el nombre" autocomplete="off"/>
                                            <button type="button" title="Consulta usuarios" style="padding: 1px; flex: 0 0 auto;" class="btn-accion-tabla consultausuario tooltipsC"
                                                data-ptrusuario_id="#usuario_habilitado_id" data-ptrnombre="#nombre_usuario_habilitado" data-ptrusuario_codigo="#usuario_habilitado_codigo">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px;" class="nombreusuario form-control" id="nombre_usuario_habilitado" value="" placeholder="Nombre usuario" readonly/>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="observacion_habilitacion">Observaciones</label>
                                        <textarea name="observacion" id="observacion_habilitacion" class="form-control" rows="2" maxlength="2000"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success" id="btn-submit-habilitar">Habilitar turno</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div id="wrap-solapas-cierre" class="d-none">
                        <ul class="nav nav-tabs gastro-tab-cierres mb-0" id="tabs-cierre-turno" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="tab-parcial-link" data-toggle="tab" href="#tab-cierre-parcial" role="tab"
                                   aria-controls="tab-cierre-parcial" aria-selected="true">
                                    <i class="fa fa-list-alt text-warning" aria-hidden="true"></i>
                                    <span>Cierre parcial (provisorio)</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tab-definitivo-link" data-toggle="tab" href="#tab-cierre-definitivo" role="tab"
                                   aria-controls="tab-cierre-definitivo" aria-selected="false">
                                    <i class="fa fa-lock text-danger" aria-hidden="true"></i>
                                    <span>Cierre definitivo</span>
                                </a>
                            </li>
                        </ul>

                        <div id="panel-numeracion-fiscal-turno" class="mb-3 d-none"></div>

                        <div class="tab-content mb-3" id="tabs-cierre-turno-content">
                            <div class="tab-pane fade show active" id="tab-cierre-parcial" role="tabpanel">
                                @if ($puede_cierre_parcial ?? false)
                                    <div class="alert alert-warning border-warning py-2 small mb-3">
                                        <strong>Cierre parcial</strong> — comprobante intermedio; el turno <strong>sigue habilitado</strong>.
                                        Use la solapa de cierre definitivo para cuadrar caja y cerrar el turno.
                                    </div>
                                    <div id="alertas-control-parcial" class="mb-3"></div>
                                    <div id="totales-tab-parcial" class="mb-3"></div>

                                    <div class="card card-outline card-primary mb-3" id="card-conciliacion-parcial">
                                        <div class="card-header py-2">
                                            <i class="fa fa-balance-scale"></i> Conciliación del turno
                                        </div>
                                        <div class="card-body py-2">
                                            <p class="small text-muted mb-2">
                                                Listado <strong>bajo demanda</strong> (40 comprobantes por página): todas las facturas del turno,
                                                o solo las que tienen diferencia si marca el filtro.
                                                En cada medio de pago use <strong>Facturas</strong> para revisar un medio puntual.
                                            </p>
                                            <div class="d-flex flex-wrap align-items-center mb-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary mr-2 js-refrescar-grilla-conciliacion" data-grilla-target="grilla-conciliacion-parcial">
                                                    <i class="fa fa-table"></i> Ver comprobantes del turno
                                                </button>
                                            <button type="button" class="btn btn-sm btn-outline-info mr-2 js-explicar-diferencias-conciliacion" data-panel-target="panel-ia-conciliacion-parcial">
                                                <i class="fa fa-magic"></i> Explicar diferencias (IA)
                                            </button>
                                            <label class="mb-0 small">
                                                <input type="checkbox" id="filtro-solo-diferencias-parcial" class="js-filtro-solo-diferencias" data-grilla-target="grilla-conciliacion-parcial"/>
                                                Solo comprobantes con diferencia
                                            </label>
                                        </div>
                                        <div id="panel-ia-conciliacion-parcial" class="d-none mb-2"></div>
                                            <div id="grilla-conciliacion-parcial" class="gastro-grilla-conciliacion-wrap">
                                                <p class="text-muted p-3 mb-0 small"><i class="fa fa-spinner fa-spin"></i> Cargando resumen…</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <button type="button" class="btn btn-outline-secondary" id="btn-informe-mozo-pdf" title="PDF informativo sin registrar cierre">
                                            <i class="fa fa-file-pdf"></i> Informe por mozo (PDF, sin cerrar)
                                        </button>
                                        <button type="button" class="btn btn-warning" id="btn-submit-cierre-parcial">
                                            <i class="fa fa-list-alt"></i> Registrar cierre parcial completo
                                        </button>
                                    </div>
                                    <p class="small text-muted mb-2">
                                        El <strong>informe por mozo</strong> muestra solo totales por mozo con leyenda
                                        «NO CIERRA EL TURNO» en el PDF. El <strong>cierre parcial completo</strong> guarda el comprobante en el historial.
                                    </p>
                                    <h6 class="font-weight-bold">Cierres parciales emitidos en este turno</h6>
                                    <div id="lista-cierres-parciales">
                                        <p class="text-muted small mb-0">Sin cierres parciales registrados.</p>
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="tab-cierre-definitivo" role="tabpanel">
                                <div class="alert alert-danger border-danger py-2 small mb-3">
                                    <strong>Cierre definitivo</strong> —
                                    cierra el turno en esta terminal. Debe cuadrar caja y no quedar cuentas sin facturar.
                                </div>

                                <div id="alertas-control-definitivo" class="mb-3"></div>
                                <div id="totales-tab-definitivo" class="mb-3"></div>

                                <div class="card card-outline card-primary mb-3" id="card-conciliacion-turno">
                                    <div class="card-header">
                                        <i class="fa fa-balance-scale"></i> Listado de comprobantes del turno
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted">
                                            No se cargan todas las facturas automáticamente. Pulse
                                            <strong>Ver comprobantes (paginado)</strong> cuando necesite el detalle;
                                            columnas con el <strong>nombre</strong> del medio de pago.
                                        </p>
                                        <div class="d-flex flex-wrap align-items-center mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary mr-2 js-refrescar-grilla-conciliacion" data-grilla-target="grilla-conciliacion-turno">
                                                <i class="fa fa-table"></i> Ver comprobantes del turno
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info mr-2 js-explicar-diferencias-conciliacion" data-panel-target="panel-ia-conciliacion-turno">
                                                <i class="fa fa-magic"></i> Explicar diferencias (IA)
                                            </button>
                                            <label class="mb-0 small">
                                                <input type="checkbox" id="filtro-solo-diferencias-definitivo" class="js-filtro-solo-diferencias" data-grilla-target="grilla-conciliacion-turno"/>
                                                Solo comprobantes con diferencia
                                            </label>
                                        </div>
                                        <div id="panel-ia-conciliacion-turno" class="d-none mb-2"></div>
                                        <div id="grilla-conciliacion-turno" class="gastro-grilla-conciliacion-wrap">
                                            <p class="text-muted p-3 mb-0 small"><i class="fa fa-spinner fa-spin"></i> Cargando resumen…</p>
                                        </div>
                                    </div>
                                </div>

                                @if ($puede_cerrar ?? false)
                                    <div class="card card-outline card-danger" id="card-cerrar">
                                        <div class="card-header bg-danger text-white">
                                            <i class="fa fa-lock"></i> Cierre definitivo del turno
                                        </div>
                                        <div class="card-body">
                                            <form id="form-cerrar-turno" autocomplete="off">
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label for="redondeo_invitaciones">Redondeo invitaciones ($0,01)</label>
                                                        <input type="number" step="0.01" name="redondeo_invitaciones" id="redondeo_invitaciones" class="form-control"/>
                                                        <small class="text-muted">Incluye invitaciones y ajuste de conciliación (hasta $0,02, p. ej. NC en otra PC).</small>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label for="redondeo_turno">Redondeo turno</label>
                                                        <input type="number" step="0.01" name="redondeo_turno" id="redondeo_turno" class="form-control" value="0"/>
                                                    </div>
                                                    <div class="form-group col-md-4">
                        <label for="sobrante_faltante">Sobrante / faltante</label>
                        <input type="number" step="0.01" name="sobrante_faltante" id="sobrante_faltante" class="form-control" value="0"/>
                        <small class="text-muted">Positivo = sobrante, negativo = faltante. Se ajusta solo al modificar el efectivo contado arriba.</small>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="observacion_cierre">Observaciones cierre</label>
                                                    <textarea name="observacion_cierre" id="observacion_cierre" class="form-control" rows="2" maxlength="2000"></textarea>
                                                </div>
                                                <div id="errores-cierre-turno" class="alert alert-warning d-none"></div>
                                                <button type="submit" class="btn btn-danger" id="btn-submit-cerrar">
                                                    <i class="fa fa-lock"></i> Confirmar cierre definitivo
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@include('includes.admin.modalconsultausuario')

<div class="modal fade" id="modal-conciliacion-medio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-conciliacion-medio-titulo">Facturas por medio de pago</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 gastro-totales-tabla">
                        <thead class="thead-light">
                            <tr>
                                <th id="modal-conc-th-comprobante">Comprobante</th>
                                <th id="modal-conc-th-hora">Hora</th>
                                <th id="modal-conc-th-cliente">Cliente</th>
                                <th id="modal-conc-th-mozo">Mozo</th>
                                <th id="modal-conc-th-total" class="text-right">Facturado</th>
                                <th id="modal-conc-th-extra" class="text-right">Este medio</th>
                                <th id="modal-conc-th-cobrado" class="text-right">Cobrado total</th>
                                <th id="modal-conc-th-acciones"></th>
                            </tr>
                        </thead>
                        <tbody id="modal-conciliacion-medio-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-canjes-premio-turno" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Canjes de premios Wigos — turno / jornada</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="ht-canjes-premio-error" class="alert alert-danger py-2 small d-none"></div>
                <div class="table-responsive gastro-grilla-conciliacion-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Cupón</th>
                                <th>Factura</th>
                                <th>SKU</th>
                                <th>Artículo</th>
                                <th class="text-right">Cant.</th>
                                <th class="text-right">Puntos</th>
                                <th>Mozo</th>
                                <th>Documento</th>
                                <th>Fecha canje</th>
                            </tr>
                        </thead>
                        <tbody id="ht-canjes-premio-body">
                            <tr><td colspan="9" class="text-muted small">Sin datos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-tickets-tarjeta-turno" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Tickets tarjeta canjeados — turno / jornada</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div id="ht-tickets-tarjeta-error" class="alert alert-danger py-2 small d-none"></div>
                <div class="table-responsive gastro-grilla-conciliacion-wrap">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Movimiento</th>
                                <th>Nº ticket</th>
                                <th>Factura</th>
                                <th>Documento</th>
                                <th class="text-right">Importe</th>
                                <th>Fecha emisión</th>
                                <th>Canje ERP</th>
                            </tr>
                        </thead>
                        <tbody id="ht-tickets-tarjeta-body">
                            <tr><td colspan="7" class="text-muted small">Sin datos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@if ($puede_anular_cierre ?? false)
<div class="modal fade" id="modal-anular-cierre" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-danger text-white">
                <h6 class="modal-title"><i class="fa fa-undo"></i> Anular último cierre definitivo</h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <form id="form-anular-cierre" autocomplete="off">
                <div class="modal-body py-3">
                    <p class="text-muted small mb-2">
                        Revierte el último cierre de <strong>{{ $identificador_pc }}</strong> en la jornada activa.
                        El turno vuelve a <strong>habilitado</strong>. Queda registrado en el log del sistema.
                    </p>
                    <div id="anular-cierre-detalle" class="mb-3"></div>
                    <div class="form-group mb-2">
                        <label for="motivo_anular_cierre">Motivo (obligatorio)</label>
                        <textarea name="motivo" id="motivo_anular_cierre" class="form-control" rows="2"
                                  maxlength="500" required placeholder="Ej.: cierre por error de conciliación"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label for="confirmacion_anular_cierre" class="requerido">Confirmación</label>
                        <input type="text" class="form-control" id="confirmacion_anular_cierre"
                               name="confirmacion" required autocomplete="off"/>
                        <small class="form-text text-muted" id="hint-confirmacion-anular-cierre"></small>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger" id="btn-submit-anular-cierre">
                        <i class="fa fa-undo"></i> Anular cierre y reabrir turno
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
