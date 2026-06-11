@extends("theme.$theme.layout")

@section('titulo')
    Proceso facturación estacionamiento
@endsection

@section('styles')
<style>
    .est-cuenta-activa-bar {
        position: sticky;
        top: 0;
        z-index: 1030;
        border-left: 4px solid #17a2b8;
        background: linear-gradient(90deg, #d1ecf1 0%, #f8fdff 100%);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        margin-bottom: 0.5rem !important;
        padding-top: 0.35rem !important;
        padding-bottom: 0.35rem !important;
    }
    .est-cuenta-activa-bar.est-procesando {
        border-left-color: #ffc107;
        background: linear-gradient(90deg, #fff3cd 0%, #fffdf5 100%);
    }
    .est-cuenta-activa-bar .est-cuenta-proceso-msg {
        font-size: 0.875rem;
        color: #856404;
        font-weight: 600;
    }
    #modal-est-aviso .est-aviso-detalle {
        white-space: pre-wrap;
        word-break: break-word;
    }
    .est-categoria-bar {
        border-left: 4px solid #ffc107;
        background: #fff8e1;
        padding: 0.5rem 0.75rem;
        margin-bottom: 0.75rem;
        border-radius: 0.25rem;
    }
    .est-categoria-bar.sin-categoria {
        border-left-color: #dc3545;
        background: #fdecea;
    }
    .est-categoria-bar .est-categoria-nombre {
        font-size: 1.15rem;
        font-weight: 700;
        color: #856404;
    }
    .est-categoria-bar.sin-categoria .est-categoria-nombre {
        color: #721c24;
    }
    .est-columnas-principales { align-items: stretch; }
    .est-columnas-principales > [class*="col-"] {
        display: flex;
        flex-direction: column;
    }
    .est-card-articulos, .est-card-detalle {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .est-panel-lineas {
        flex: 0 1 auto;
        max-height: 38vh;
        overflow-y: auto;
        min-height: 0;
    }
    #panel-cobranza-compacta {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: #f8f9fa;
        padding: 0.5rem;
    }
    #panel-cobranza-compacta .est-cobranza-scroll {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 140px;
    }
    #est-items-iconos {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
        gap: 0.45rem;
        margin-top: 0.5rem;
        max-height: 220px;
        overflow-y: auto;
        padding: 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: #fff;
    }
    #est-items-iconos .est-item-icono {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 72px;
        padding: 0.35rem 0.25rem;
        border: 2px solid #dee2e6;
        border-radius: 0.4rem;
        background: #fff;
        cursor: pointer;
        font-size: 0.68rem;
        line-height: 1.15;
        text-align: center;
        word-break: break-word;
        transition: border-color 0.12s, background 0.12s;
    }
    #est-items-iconos .est-item-icono:hover {
        border-color: #17a2b8;
        background: #e8f7fa;
    }
    #est-items-iconos .est-item-icono .est-item-id {
        font-weight: 700;
        font-size: 0.75rem;
        color: #495057;
    }
    #est-items-iconos .est-item-icono .est-item-precio {
        font-size: 0.65rem;
        color: #28a745;
        margin-top: 0.15rem;
    }
    .est-campo-consulta {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }
    .est-campo-consulta .est-campo-id { width: 72px; flex: 0 0 72px; }
    .est-campo-consulta .est-campo-codigo { width: 88px; flex: 0 0 88px; }
    .est-campo-consulta .est-campo-nombre { flex: 1 1 auto; min-width: 0; }
    #est-cuenta-table th, #est-cuenta-table td { vertical-align: middle; }
    #est-medios-rapidos {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: flex-start;
    }
    #est-medios-rapidos .est-medio-rapido {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 72px;
        max-width: 110px;
        padding: 0.35rem 0.4rem 0.25rem;
        font-size: 0.68rem;
        line-height: 1.15;
        text-align: center;
        white-space: normal;
        word-break: break-word;
    }
    #est-medios-rapidos .est-medio-rapido i,
    #est-medios-rapidos .est-medio-rapido .gastro-icon-mercadopago {
        font-size: 1.15rem;
        margin-bottom: 0.15rem;
    }
    .gastro-icon-mercadopago {
        display: inline-block;
        width: 1.15rem;
        height: 1.15rem;
        background: url('{{ asset('assets/pages/img/ventas/gastronomia/mercadopago.svg') }}') center/contain no-repeat;
    }
    #est-cuenta-table .consultacuentacaja i,
    #est-cuenta-table .consultacuentacaja .gastro-icon-mercadopago {
        font-size: 1rem;
    }
    #est-cuenta-table .consultacuentacaja .gastro-icon-mercadopago {
        width: 1rem;
        height: 1rem;
    }
    .est-totales-resumen { font-size: 1rem; }
    .est-totales-resumen .est-total-diff { color: #dc3545; font-weight: normal; }
    #modal-f8-descuento #est-descuento-slot-modal,
    #modal-f8-descuento #est-descuento-movable {
        display: block;
        width: 100%;
    }
    #modal-f8-descuento #est-descuento-movable .form-group {
        margin-bottom: 0.75rem;
    }
    #modal-f8-descuento .est-campo-consulta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
    }
    #modal-f8-descuento .btn-accion-tabla {
        flex-shrink: 0;
    }
    #modal-f8-descuento .est-campo-id {
        width: 72px;
        flex: 0 0 72px;
    }
    #modal-f8-descuento .est-campo-codigo {
        width: 110px;
        flex: 0 0 110px;
    }
    #modal-f8-descuento .est-campo-nombre {
        flex: 1 1 180px;
        min-width: 0;
    }
    #est-descuento-en-modal-aviso {
        font-size: 0.85rem;
        color: #6c757d;
        font-style: italic;
    }
    body.modal-open #consultaclienteModal.show.est-modal-sobre-f8,
    body.modal-open #consultadescuentoModal.show.est-modal-sobre-f8 {
        z-index: 1070;
    }
</style>
@endsection

@section('scripts')
<script>
    window.ESTACIONAMIENTO = {
        empresaId: {{ (int) ($empresa_id ?? 0) }},
        csrf: @json(csrf_token()),
        tieneCfgPv: @json($tiene_cfg_pv),
        usocuentacajaEstacionamientoId: {{ (int) ($usocuentacaja_estacionamiento_id ?? 0) }},
        monedaFacturaId: {{ (int) config('estacionamiento.moneda_factura_id', 1) }},
        wsfeReceptorCfUmbralMonto: {{ (float) $wsfe_receptor_cf_umbral_monto }},
        wsfeForzarModoCaea: @json($wsfe_forzar_modo_caea),
        clienteDescuentoCodigo: @json($cliente_descuento_codigo ?? '501'),
        clienteDescuento: @json($cliente_descuento ?? null),
        jornadaObligatoria: @json($jornada_obligatoria ?? true),
        jornada: @json($jornada),
        requiereHabilitacionTurno: @json($requiere_habilitacion_turno ?? true),
        turnoOperativo: @json($turno_operativo ?? null),
        urlHabilitacionTurno: @json($url_habilitacion_turno),
        sincronizarAnitaAlFacturar: @json(config('estacionamiento.sincronizar_anita_al_facturar', false)),
        ticketImpresionAutomatica: @json(config('estacionamiento.ticket_impresion_automatica', true)),
        rutas: {
            apiBase: @json(url('caja/estacionamiento/api')),
            descuentoLeer: @json(url('caja/estacionamiento/descuento/leer')),
            facturasDia: @json(route('estacionamiento_facturas_dia')),
            crearCobranzaBase: @json(url('caja/cobranza/crear')),
        },
    };
</script>
@php
    $estJsBase = rtrim((string) config('app.app_carpeta'), '/') . '/assets/pages/scripts';
@endphp
<script src="{{ $estJsBase }}/caja/cuentacaja/consulta.js?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}"></script>
<script src="{{ $estJsBase }}/ventas/cliente/consulta.js?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}"></script>
<script src="{{ $estJsBase }}/caja/estacionamiento/descuento/consulta.js?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/descuento/consulta.js')) ?: time() }}"></script>
<script src="{{ $estJsBase }}/caja/estacionamiento/proceso_facturacion.js?v={{ @filemtime(public_path('assets/pages/scripts/caja/estacionamiento/proceso_facturacion.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (!$tiene_cfg_pv)
            <div class="alert alert-warning">
                No hay configuración de punto de venta estacionamiento para el identificador PC actual
                (<code>{{ $identificador_pc_actual }}</code>).
                Configure en
                <a href="{{ route('consultar_configuracion_puntoventa_estacionamiento') }}">Config. punto de venta estacionamiento</a>
                una fila con ese identificador.
            </div>
        @endif

        <div class="alert alert-info py-2 mb-3">
            @if ($tiene_cfg_pv && $empresa_nombre)
                Terminal: <strong>{{ $identificador_pc_actual }}</strong>
                · Empresa: <strong>{{ $empresa_nombre }}</strong>
                @if ($cfg_tipotransaccion_nombre ?? null)
                    · Tipo factura: <strong>{{ $cfg_tipotransaccion_nombre }}</strong>
                @endif
            @endif
            @if (! empty($jornada['jornada_abierta']))
                · Jornada <strong>{{ $jornada['fecha_jornada_fmt'] ?? $jornada['fecha_jornada'] }}</strong> abierta
            @endif
            · <kbd>F5</kbd> Facturar (efectivo si no hay medios)
            · <kbd>F8</kbd> Facturar con descuento
        </div>

        @if (($jornada_obligatoria ?? true) && $tiene_cfg_pv && empty($jornada['jornada_abierta']))
            <div class="alert alert-danger py-2 mb-3" id="est-alerta-sin-jornada">
                No hay jornada abierta.
                <a href="{{ route('estacionamiento_jornada', ['empresa_id' => $empresa_id]) }}">Abrir jornada</a>
                antes de facturar.
            </div>
        @endif

        @if (($requiere_habilitacion_turno ?? true) && $tiene_cfg_pv)
            <div class="alert py-2 mb-3 {{ empty($turno_operativo['turno_habilitado']) ? 'alert-danger' : 'alert-secondary' }}" id="est-alerta-turno">
                @if (empty($turno_operativo['turno_habilitado']))
                    No hay turno habilitado en esta terminal.
                    <a href="{{ route('estacionamiento_habilitacion_turno') }}">Habilitar turno</a>.
                @else
                    Turno <strong>{{ $turno_operativo['turno_nombre'] ?? '' }}</strong>
                    — {{ $turno_operativo['usuario_habilitado'] ?? '' }}
                    — Habilitado {{ $turno_operativo['habilitacion_en_fmt'] ?? '' }}
                @endif
            </div>
        @endif

        <div id="est-bar-cuenta-activa" class="est-cuenta-activa-bar callout callout-info d-none" role="status" aria-live="polite">
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                <span class="text-muted small flex-shrink-0">Cuenta activa:</span>
                <span id="est-cuenta-activa-linea" class="font-weight-bold">—</span>
                <span class="badge badge-info" id="est-cuenta-activa-estado">ABIERTA</span>
                <span id="est-cuenta-proceso-msg" class="est-cuenta-proceso-msg d-none"></span>
            </div>
        </div>

        <div id="est-bar-categoria" class="est-categoria-bar sin-categoria mb-3">
            <div class="d-flex flex-wrap align-items-center" style="gap: 0.75rem;">
                <span class="text-muted small">Categoría vehículo:</span>
                <select id="est-categoria-select" class="form-control form-control-sm" style="max-width: 280px;">
                    <option value="">— Seleccione categoría —</option>
                </select>
                <span id="est-categoria-nombre-visible" class="est-categoria-nombre d-none"></span>
                <small id="est-categoria-hint-cambio" class="text-muted">Cambie la categoría si ingresa otro vehículo</small>
            </div>
        </div>

        <div class="row est-columnas-principales">
            <div class="col-xl-5">
                <div class="card card-outline card-info mb-3 est-card-articulos">
                    <div class="card-header py-2">
                        <span><i class="fa fa-car"></i> Ítems estacionamiento</span>
                    </div>
                    <div class="card-body py-2">
                        <p class="small text-muted mb-2">
                            Ingrese el <strong>ID</strong> del ítem, use la lupa o seleccione un icono.
                            <strong>Enter</strong> agrega con cantidad 1; <strong>+</strong> o el botón Agregar abren cantidad.
                        </p>
                        <div class="est-campo-consulta mb-2">
                            <input type="text" id="est-item-id-input" class="form-control form-control-sm est-campo-id" placeholder="ID" inputmode="numeric" autocomplete="off">
                            <button type="button" class="btn-accion-tabla" id="btn-est-buscar-item" title="Buscar ítem">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" id="est-item-nombre-preview" class="form-control form-control-sm est-campo-nombre" placeholder="Nombre ítem" readonly>
                            <button type="button" class="btn btn-sm btn-success" id="btn-est-agregar-item">
                                <i class="fa fa-plus"></i> Agregar
                            </button>
                        </div>
                        <div id="est-items-iconos" aria-label="Selección rápida de ítems"></div>
                        <p id="est-items-vacio" class="text-muted small mb-0 mt-2 d-none">Seleccione categoría para ver ítems con precio.</p>
                    </div>
                </div>

                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-2"><span><i class="fa fa-user"></i> Datos de facturación</span></div>
                    <div class="card-body py-2">
                        <div class="form-group mb-2">
                            <label class="small mb-0">Patente <span class="text-muted">(opcional)</span></label>
                            <input type="text" id="est-patente" class="form-control form-control-sm text-uppercase" maxlength="20" autocomplete="off">
                        </div>
                        <div class="form-group mb-2">
                            <label class="small mb-0">Cliente para facturar <span class="text-muted">(vacío = CF)</span></label>
                            <div class="est-campo-consulta">
                                <input type="text" class="form-control form-control-sm est-campo-id" id="cliente_id" placeholder="ID" autocomplete="off">
                                <button type="button" title="Consulta clientes" class="btn-accion-tabla consultacliente tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm est-campo-codigo codigocliente" id="codigocliente" placeholder="Código" autocomplete="off">
                                <input type="text" class="form-control form-control-sm est-campo-nombre" id="nombrecliente" placeholder="Nombre" readonly>
                            </div>
                        </div>
                        <div id="panel-factura-receptor-manual" class="mb-2 d-none">
                            <label class="small mb-0 text-primary">Receptor manual</label>
                            <div class="form-row mt-1">
                                <div class="col-md-5 mb-1">
                                    <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-nombre" placeholder="Nombre">
                                </div>
                                <div class="col-md-3 mb-1">
                                    <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-documento" placeholder="Documento">
                                </div>
                                <div class="col-md-4 mb-1">
                                    <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-domicilio" placeholder="Domicilio">
                                </div>
                            </div>
                        </div>
                        <div id="est-descuento-slot-original">
                            <div id="est-descuento-en-modal-aviso" class="d-none mb-2">Descuento y cliente interno se cargan en el modal central (F8).</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger mr-1" id="btn-est-cerrar-cuenta">
                            <i class="fa fa-times"></i> Cerrar cuenta
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="btn-est-guardar-cabecera">
                            <i class="fa fa-save"></i> Guardar datos
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card card-outline card-dark mb-3 est-card-detalle">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                        <span><i class="fa fa-list"></i> Consumos / cobranza</span>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-success" id="tool-facturar" title="Facturar (F5)">
                                    <i class="fa fa-file-invoice-dollar"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="tool-descuento" title="Descuento (F8)">
                                    <i class="fa fa-percent"></i>
                                </button>
                                <a href="{{ route('estacionamiento_facturas_dia') }}" class="btn btn-outline-primary" title="Facturas del día">
                                    <i class="fa fa-calendar-day"></i>
                                </a>
                                @if ($requiere_habilitacion_turno ?? true)
                                <a href="{{ route('estacionamiento_habilitacion_turno') }}" class="btn btn-outline-warning" title="Habilitación / cierre turno">
                                    <i class="fa fa-lock"></i>
                                </a>
                                @endif
                            </div>
                            <span id="est-facturacion-loading" style="display:none; color:#6c757d; font-size:0.95em; white-space:nowrap;" aria-live="polite">
                                <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                                <span class="est-facturacion-loading-text">Facturando…</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body py-2 d-flex flex-column" style="min-height: 420px;">
                        <div id="panel-detalle-lineas" class="est-panel-lineas"></div>
                        <div id="panel-cobranza-compacta" class="small mt-2">
                            <input type="hidden" id="est-empresa-id" value="{{ (int) ($empresa_id ?? 0) }}">
                            <input type="hidden" id="factura-moneda-id" value="">
                            <input type="hidden" id="empresa_id" value="{{ (int) ($empresa_id ?? 0) }}">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-1">
                                <strong>Cobranza</strong>
                                <span class="text-muted" style="font-size:11px;">Se graba al facturar · <kbd>F5</kbd> efectivo si vacío</span>
                            </div>
                            <div class="table-responsive est-cobranza-scroll">
                                <table class="table table-sm table-bordered mb-0 bg-white" id="est-cuenta-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 42%;">Cuenta de caja</th>
                                            <th style="width: 8%;">Mon.</th>
                                            <th style="width: 18%;">Monto</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-est-cuenta-table"></tbody>
                                </table>
                            </div>
                            <div class="mt-1 d-flex flex-wrap align-items-start" style="gap: 0.35rem;">
                                <button type="button" class="btn btn-sm btn-danger" id="est-agrega-renglon-cuenta">+ Agregar renglón</button>
                                <div id="est-medios-rapidos" class="d-none"></div>
                                <div id="est-totales-cobranza" class="est-totales-resumen ml-auto"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="est-facturacion-procesando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 18rem;">
        <i class="fa fa-spinner fa-spin fa-2x text-warning mb-2" aria-hidden="true"></i>
        <div><strong id="est-facturacion-procesando-titulo">Procesando…</strong></div>
        <div class="small text-muted mt-1" id="est-facturacion-procesando-subtitulo">Por favor espere. No cierre ni recargue la página.</div>
    </div>
</div>

@include('includes.ventas.modalconsultacliente')
@include('includes.caja.modalconsultacuentacaja')
@include('includes.caja.modalconsultadescuento_estacionamiento')

<template id="est-template-renglon-cuenta">
    <tr class="item-cuenta-est">
        <td>
            <div class="d-flex align-items-center" style="gap:4px;">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" class="btn-accion-tabla consultacuentacaja tooltipsC" title="Consulta cuentas caja">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigo" style="width:72px;" placeholder="Cód." autocomplete="off">
                <input type="text" class="form-control form-control-sm nombre flex-grow-1" placeholder="Cuenta" readonly>
            </div>
        </td>
        <td><input type="text" class="form-control form-control-sm moneda-abrev text-center" readonly></td>
        <td><input type="text" class="form-control form-control-sm monto text-right" inputmode="decimal" autocomplete="off"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger est-quitar-renglon-cobranza" title="Quitar">&times;</button>
        </td>
    </tr>
</template>

<div class="modal fade" id="modal-f8-descuento" tabindex="-1" role="dialog" aria-labelledby="modalF8Titulo" data-backdrop="true" data-keyboard="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modalF8Titulo">Facturar con descuento estacionamiento</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-3">
                    Indique el código de descuento (Enter o lupa) y el cliente interno si corresponde.
                    Si el descuento no es del 100%, al confirmar complete el medio de cobro en la grilla y pulse F8 de nuevo.
                </p>
                <div id="est-descuento-slot-modal">
                    <div id="est-descuento-movable">
                        <div class="form-group mb-2">
                            <label class="small mb-0">Descuento estacionamiento</label>
                            <div class="est-campo-consulta">
                                <input type="text" class="form-control form-control-sm est-campo-id descuento_estacionamiento_id" id="descuento_estacionamiento_id" placeholder="ID" autocomplete="off">
                                <button type="button" title="Consulta descuentos" class="btn-accion-tabla consultadescuento tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm est-campo-codigo codigodescuento" id="codigodescuento" placeholder="Código" autocomplete="off">
                                <input type="text" class="form-control form-control-sm est-campo-nombre nombredescuento" id="nombredescuento" placeholder="Nombre" readonly>
                            </div>
                        </div>
                        <div id="panel-cliente-descuento" class="form-group mb-2">
                            <label class="small mb-0 text-primary">Cliente interno del descuento <span class="text-danger">*</span></label>
                            <div class="est-campo-consulta mt-1">
                                <input type="text" class="form-control form-control-sm est-campo-id" id="cliente_descuento_id" placeholder="ID" autocomplete="off">
                                <button type="button" title="Consulta cliente interno (invita / centro de costo)" class="btn-accion-tabla consultaclienteinternodescuento tooltipsC">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm est-campo-codigo codigoclienteinternodescuento" id="codigocliente_descuento" placeholder="Código" autocomplete="off">
                                <input type="text" class="form-control form-control-sm est-campo-nombre nombreclienteinternodescuento" id="nombrecliente_descuento" placeholder="Nombre / razón social" readonly>
                            </div>
                            <small class="form-text text-muted">Quien invita o centro de costos. <strong>No</strong> es el cliente de la factura.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="modal-f8-descuento-confirmar">Facturar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-est-cantidad" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Cantidad</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-2">
                <input type="number" step="any" min="0.0001" class="form-control" id="est-fld-cantidad-linea" value="1">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-primary" id="modal-est-cantidad-confirmar">
                    Continuar <small class="text-white-50">(Enter)</small>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-est-aviso" tabindex="-1" role="dialog" aria-labelledby="modal-est-aviso-titulo" data-backdrop="static" data-keyboard="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" id="modal-est-aviso-header">
                <h5 class="modal-title" id="modal-est-aviso-titulo">Aviso</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="est-aviso-detalle d-none" id="modal-est-aviso-detalle"></div>
                <div id="modal-est-aviso-body"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary btn-sm" id="modal-est-aviso-aceptar" data-dismiss="modal">Aceptar</button>
            </div>
        </div>
    </div>
</div>
@endsection
