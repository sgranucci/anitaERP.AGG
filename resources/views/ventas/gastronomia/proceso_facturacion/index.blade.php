@extends("theme.$theme.layout")

@section('titulo')
    Proceso facturación gastronomía
@endsection

@section('styles')
<style>
    .gastro-cuenta-activa-bar {
        position: sticky;
        top: 0;
        z-index: 1030;
        border-left: 4px solid #28a745;
        background: linear-gradient(90deg, #d4edda 0%, #f8fff9 100%);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        margin-bottom: 0.5rem !important;
        padding-top: 0.35rem !important;
        padding-bottom: 0.35rem !important;
    }
    .gastro-cuenta-activa-bar .gastro-cuenta-activa-linea {
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.25;
        color: #155724;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        min-width: 0;
        flex: 1 1 auto;
    }
    .gastro-cuenta-activa-bar .gastro-cuenta-activa-estado {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        padding: 0.25em 0.5em;
        flex-shrink: 0;
    }
    .gastro-header-cuenta-chip {
        font-size: 1.05rem;
        font-weight: 700;
        padding: 0.35rem 0.75rem;
        border-radius: 0.25rem;
        white-space: nowrap;
    }
    .gastro-header-cuenta-chip.es-mesa {
        background: #ffc107;
        color: #212529;
    }
    .gastro-header-cuenta-chip.es-cuenta {
        background: #17a2b8;
        color: #fff;
    }
    .gastro-indicador-cuenta {
        font-size: 1rem;
        font-weight: 600;
        padding: 0.4em 0.75em;
        vertical-align: middle;
    }
    #panel-mesas .btn-gastro-mesa-activa,
    #panel-cuentas .btn-gastro-cuenta-activa {
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.45);
        font-weight: 700;
    }
    .gastro-campo-consulta {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }
    .gastro-campo-consulta .gastro-campo-id {
        width: 72px;
        flex: 0 0 72px;
    }
    .gastro-campo-consulta .gastro-campo-codigo {
        width: 88px;
        flex: 0 0 88px;
    }
    .gastro-campo-consulta .gastro-campo-nombre {
        flex: 1 1 auto;
        min-width: 0;
    }
    .gastro-campo-consulta .btn-accion-tabla {
        flex: 0 0 auto;
    }
    .gastro-fila-cubiertos-mozo .col {
        min-width: 0;
    }
    .gastro-columnas-principales {
        align-items: stretch;
    }
    .gastro-columnas-principales > [class*="col-"] {
        display: flex;
        flex-direction: column;
    }
    .gastro-card-consumo-carga,
    .gastro-card-detalle-cuenta {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .gastro-card-consumo-carga .card-body,
    .gastro-card-detalle-cuenta .card-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    .gastro-aviso-caea {
        font-size: 0.85rem;
        line-height: 1.35;
        text-align: right;
        margin-top: auto;
        margin-left: auto;
        margin-bottom: 0;
        max-width: 100%;
        align-self: flex-end;
    }
    .gastro-aviso-caea code {
        font-size: 0.8em;
    }
    .gastro-panel-lineas {
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
    #panel-cobranza-compacta .gastro-cobranza-scroll {
        flex: 1 1 auto;
        overflow-y: auto;
        min-height: 140px;
    }
    #gastro-cuenta-table th,
    #gastro-cuenta-table td {
        vertical-align: middle;
    }
    #gastro-cobranza-cotiz-bar {
        font-size: 11px;
        line-height: 1.3;
        padding: 0.2rem 0.45rem;
        margin-bottom: 0.35rem;
        border-radius: 0.2rem;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        color: #856404;
    }
    #gastro-cuenta-table .gastro-cc-cuenta-wrap {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
        min-width: 0;
    }
    #gastro-cuenta-table .gastro-cc-codigo {
        width: 72px;
        flex: 0 0 72px;
    }
    #gastro-cuenta-table .gastro-cc-nombre {
        flex: 1 1 auto;
        min-width: 0;
    }
    .gastro-totales-resumen {
        font-size: 1rem;
    }
    .gastro-totales-resumen .gastro-total-diff {
        color: #dc3545;
        font-weight: normal;
    }
    #gastro-cuenta-table .gastro-cc-moneda {
        width: 56px;
        text-align: center;
        font-weight: 600;
        color: #495057;
    }
    #gastro-cuenta-table .gastro-cc-monto {
        width: 110px;
    }
    #modal-gastro-aviso .modal-body {
        max-height: min(70vh, 520px);
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.95rem;
        line-height: 1.45;
    }
    #modal-gastro-aviso .gastro-aviso-detalle {
        font-size: 0.9rem;
        color: #495057;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #dee2e6;
    }
    #gastro-iframe-impresion-factura {
        position: absolute;
        width: 0;
        height: 0;
        border: 0;
        left: -9999px;
        top: -9999px;
    }
    #modal-f8-descuento .gastro-campo-consulta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem;
    }
    #modal-f8-descuento .gastro-campo-id {
        width: 72px;
        flex: 0 0 72px;
    }
    #modal-f8-descuento .gastro-campo-codigo {
        width: 110px;
        flex: 0 0 110px;
    }
    #modal-f8-descuento .gastro-campo-nombre {
        flex: 1 1 180px;
        min-width: 0;
    }
    #gastro-descuento-en-modal-aviso {
        font-size: 0.85rem;
        color: #6c757d;
        font-style: italic;
    }
</style>
@endsection

@section('scripts')
<script>
    window.GASTRONOMIA = {
        empresaId: {{ (int) $empresa_id }},
        prefijoSku: @json($prefijo_sku),
        skuCatalogoDigitosSufijo: {{ (int) $sku_catalogo_digitos_sufijo }},
        csrf: @json(csrf_token()),
        rutas: {
            crearCobranzaBase: @json(url('caja/cobranza/crear')),
            listaPdfFacturaBase: @json(url('ventas/listaunafactura')),
        },
        tieneCfgPv: @json($tiene_cfg_pv),
        usocuentacajaGastronomiaId: {{ (int) ($usocuentacaja_gastronomia_id ?? 0) }},
        monedaFacturaId: @json(config('gastronomia.moneda_factura_id')),
        wsfeReceptorCfUmbralMonto: {{ (float) $wsfe_receptor_cf_umbral_monto }},
        wsfeForzarModoCaea: @json($wsfe_forzar_modo_caea),
        cuentacajaEfectivo: null,
        modoSeleccionPreferido: @json($modo_seleccion_preferido ?? 'mesa'),
        cuentasLibresHabilitadas: @json($cuentas_libres_habilitadas ?? true),
        cubiertosObligatorioAlAbrir: @json($cubiertos_obligatorio_al_abrir ?? true),
        cubiertosDefaultAlAbrir: {{ (int) ($cubiertos_default_al_abrir ?? 1) }},
        mozoObligatorioAlAbrir: @json($mozo_obligatorio_al_abrir ?? true),
        jornadaObligatoria: @json($jornada_obligatoria ?? true),
        jornada: @json($jornada),
        urlJornada: @json(route('gastronomia_jornada')),
        requiereHabilitacionTurno: @json($requiere_habilitacion_turno ?? true),
        turnoOperativo: @json($turno_operativo ?? null),
        urlHabilitacionTurno: @json(route('gastronomia_habilitacion_turno')),
        rutasTurno: {
            estado: @json(url('ventas/gastronomia/api/turno-estado')),
            cierreParcial: @json(url('ventas/gastronomia/api/cierre-parcial-turno')),
            cerrar: @json(url('ventas/gastronomia/api/cerrar-turno')),
        },
        waitryHabilitado: @json(config('waitry.habilitado', false)),
        waitryGetOrdersMinutosAtras: {{ max(0, (int) config('waitry.get_orders_minutos_atras', 20)) }},
        rutasWaitry: {
            ordenesPendientes: @json(url('ventas/gastronomia/api/waitry-ordenes-pendientes')),
            importarOrden: @json(url('ventas/gastronomia/api/waitry-importar-orden')),
        },
    };
</script>
@php
    $gastroCuentasLibresHabilitadas = $cuentas_libres_habilitadas ?? true;
    $gastroWaitryHabilitado = (bool) config('waitry.habilitado', false);
    $gastroModoPreferido = $modo_seleccion_preferido ?? 'mesa';
    $gastroModoCuentas = $gastroCuentasLibresHabilitadas && $gastroModoPreferido === 'cuenta';
    $gastroModoWaitry = $gastroWaitryHabilitado && $gastroModoPreferido === 'waitry';
@endphp
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/mozo_gastronomia/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/descuento_gastronomia/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/proceso_facturacion.js') }}"></script>
@if ($requiere_habilitacion_turno ?? true)
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/totales_turno_render.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/turno_operativo_pos.js') }}"></script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')

        @if (!$tiene_cfg_pv)
            <div class="alert alert-warning">
                No hay configuración de punto de venta gastronomía para el identificador PC actual (<code>{{ $identificador_pc_actual }}</code>).
                Configure en <a href="{{ route('consultar_configuracion_puntoventa_gastronomia') }}">Config. punto de venta gastronomía</a>
                una fila con ese identificador (define la empresa, ubicación y PV de esta terminal), y/o ajuste <code>GASTRONOMIA_IDENTIFICADOR_PC</code> @if (config('gastronomia.identificador_pc_usar_ip_cliente'))
                (modo IP por terminal: <code>GASTRONOMIA_IDENTIFICADOR_USAR_IP_CLIENTE=true</code>; revise proxies nginx si la IP no es la de la PC)
                @endif.
            </div>
        @endif


        <div class="alert alert-info py-2 mb-3">
            @if ($tiene_cfg_pv && $empresa_nombre)
                Terminal: <strong>{{ $identificador_pc_actual }}</strong> · Empresa: <strong>{{ $empresa_nombre }}</strong> —
            @endif
            SKU catálogo: prefijo <strong>{{ $prefijo_sku }}</strong>
            —
            tipo transacción factura:
            @if ($cfg_tipotransaccion_nombre ?? null)
                <strong>{{ $cfg_tipotransaccion_nombre }}</strong>
            @else
                <span class="text-danger">no configurado en PV gastronomía</span>
            @endif
            @if (! empty($jornada['jornada_abierta']))
                —
                Jornada <strong>{{ $jornada['fecha_jornada'] }}</strong> abierta
                · facturas con fecha <strong>{{ $jornada['fecha_factura_hoy'] }}</strong>
            @endif
        </div>

        @if (($jornada_obligatoria ?? true) && $tiene_cfg_pv && empty($jornada['jornada_abierta']))
            <div class="alert alert-danger py-2 mb-3" id="gastro-alerta-sin-jornada">
                No hay <strong>jornada abierta</strong> para esta empresa. Abra la jornada en
                <a href="{{ route('gastronomia_jornada', ['empresa_id' => $empresa_id]) }}">Jornada gastronomía</a>
                antes de operar o facturar.
            </div>
        @endif

        @if (($requiere_habilitacion_turno ?? true) && $tiene_cfg_pv)
            <div class="alert py-2 mb-3 {{ empty($turno_operativo['turno_habilitado']) ? 'alert-danger' : 'alert-secondary' }}" id="gastro-alerta-turno">
                @if (empty($turno_operativo['turno_habilitado']))
                    No hay <strong>turno habilitado</strong> en esta terminal.
                    <a href="{{ route('gastronomia_habilitacion_turno') }}">Habilitar turno</a>
                    antes de facturar.
                @else
                    Turno <strong>{{ $turno_operativo['turno_nombre'] ?? '' }}</strong>
                    — {{ $turno_operativo['usuario_habilitado'] ?? '' }}
                    — Jornada <strong>{{ $turno_operativo['fecha_jornada_fmt'] ?? ($turno_operativo['fecha_jornada'] ?? '') }}</strong>
                    — Habilitado {{ $turno_operativo['habilitacion_en_fmt'] ?? ($turno_operativo['habilitacion_en'] ?? '') }}
                    — Monto ${{ number_format((float) ($turno_operativo['monto_habilitacion'] ?? 0), 2, ',', '.') }}
                    — parciales: {{ (int) ($turno_operativo['cierres_parciales'] ?? 0) }}
                @endif
            </div>
        @endif

        <div id="gastro-bar-cuenta-activa" class="gastro-cuenta-activa-bar callout callout-success d-none" role="status" aria-live="polite">
            <div class="d-flex align-items-center flex-nowrap" style="gap: 0.5rem;">
                <span class="text-muted small flex-shrink-0">Activa:</span>
                <span id="gastro-cuenta-activa-linea" class="gastro-cuenta-activa-linea" title="">—</span>
                <span class="badge badge-success gastro-cuenta-activa-estado">ABIERTA</span>
            </div>
        </div>

        <div class="card card-outline card-primary mb-3">
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                <span class="d-flex align-items-center flex-wrap">
                    <span><i class="fa fa-cutlery"></i> Mesa / cuenta</span>
                    <span id="gastro-header-cuenta-chip" class="gastro-header-cuenta-chip d-none ml-2" aria-hidden="true"></span>
                    <span class="text-muted small ml-2">
                        <kbd>F5</kbd> Efectivizar · <kbd>F8</kbd> Facturar con descuento (modal centrado; código + Enter; lupa opcional)
                        @if ($gastroCuentasLibresHabilitadas)
                            · <kbd>+</kbd> Nueva cuenta libre
                        @endif
                    </span>
                </span>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary{{ ($gastroModoCuentas || $gastroModoWaitry) ? '' : ' active' }}" id="btn-modo-mesa">Mesas</button>
                    @if ($gastroCuentasLibresHabilitadas)
                    <button type="button" class="btn btn-outline-secondary{{ $gastroModoCuentas ? ' active' : '' }}" id="btn-modo-cuenta">Cuentas libres</button>
                    @endif
                    @if ($gastroWaitryHabilitado)
                    <button type="button" class="btn btn-outline-secondary{{ $gastroModoWaitry ? ' active' : '' }}" id="btn-modo-waitry">Cuentas externas</button>
                    @endif
                </div>
            </div>
            <div class="card-body py-2">
                <div id="panel-mesas" class="row{{ ($gastroModoCuentas || $gastroModoWaitry) ? ' d-none' : '' }}"></div>
                <div id="panel-cuentas" class="row{{ $gastroModoCuentas ? '' : ' d-none' }}"></div>
                @if ($gastroWaitryHabilitado)
                <div id="panel-waitry" class="{{ $gastroModoWaitry ? '' : 'd-none' }}">
                    <div class="d-flex align-items-center flex-wrap mb-2" style="gap: 0.5rem;">
                        <span class="text-muted small" id="panel-waitry-filtro-leyenda">Órdenes Waitry sin pago (getOrdersPOS)</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-waitry-refrescar">
                            <i class="fa fa-refresh"></i> Actualizar
                        </button>
                    </div>
                    <div id="panel-waitry-lista" class="row"></div>
                    <p id="panel-waitry-vacio" class="text-muted small mb-0 d-none">No hay cuentas externas pendientes de facturar.</p>
                </div>
                @endif
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-success{{ $gastroModoCuentas ? '' : ' d-none' }}" id="btn-nueva-cuenta-libre"><i class="fa fa-plus"></i> Nueva cuenta</button>
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btn-cerrar-cuenta"><i class="fa fa-times"></i> Cerrar cuenta</button>
                </div>
            </div>
        </div>

        <div class="row gastro-columnas-principales">
            <div class="col-xl-5">
                <div class="card card-outline card-secondary mb-3">
                    <div class="card-header py-2 d-flex align-items-center flex-wrap">
                        <span><i class="fa fa-user"></i> Cuenta seleccionada</span>
                        <span id="gastro-indicador-cuenta" class="gastro-indicador-cuenta badge d-none ml-2"></span>
                    </div>
                    <div class="card-body py-2">
                        <div class="form-row">
                            <div class="form-group col-md-12 mb-2">
                                <label class="small mb-0">Cliente para facturar <span class="text-muted">(vacío = Consumidor Final)</span></label>
                                <div class="gastro-campo-consulta">
                                    <input type="text" class="form-control form-control-sm gastro-campo-id" id="cliente_id" name="cliente_id" value="" placeholder="ID" autocomplete="off">
                                    <button type="button" title="Consulta clientes" class="btn-accion-tabla consultacliente tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="form-control form-control-sm gastro-campo-codigo codigocliente" id="codigocliente" name="codigocliente" value="" placeholder="Código" autocomplete="off">
                                    <input type="text" class="form-control form-control-sm gastro-campo-nombre" id="nombrecliente" name="nombrecliente" value="" placeholder="Nombre / razón social" autocomplete="off" readonly>
                                </div>
                            </div>
                            <div id="panel-factura-receptor-manual" class="col-md-12 mb-2 d-none">
                                <label class="small mb-0 text-primary">Receptor manual</label>
                                <div class="form-row mt-1">
                                    <div class="form-group col-md-5 mb-1">
                                        <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-nombre" placeholder="Nombre / razón social" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-3 mb-1">
                                        <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-documento" placeholder="Documento" inputmode="numeric" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-4 mb-1">
                                        <input type="text" class="form-control form-control-sm" id="fld-factura-receptor-domicilio" placeholder="Domicilio (opcional)" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-12 mb-2">
                                <div class="form-row align-items-end gastro-fila-cubiertos-mozo">
                                    <div class="col-auto">
                                        <label class="small mb-0 d-block">Cubiertos</label>
                                        <input type="number" min="0" class="form-control form-control-sm" id="fld-cubiertos" value="{{ (int) ($cubiertos_default_al_abrir ?? 1) }}" style="width:72px;">
                                    </div>
                                    <div class="col">
                                        <label class="small mb-0 d-block">Mozo</label>
                                        <div class="gastro-campo-consulta">
                                            <input type="text" class="form-control form-control-sm gastro-campo-id mozo_gastronomia_id" id="mozo_gastronomia_id" name="mozo_gastronomia_id" value="" placeholder="ID" autocomplete="off">
                                            <button type="button" title="Consulta mozos" class="btn-accion-tabla consultamozo tooltipsC">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="form-control form-control-sm gastro-campo-codigo codigomozo" id="codigomozo" name="codigomozo" value="" placeholder="Código" autocomplete="off">
                                            <input type="text" class="form-control form-control-sm gastro-campo-nombre nombremozo" id="nombremozo" name="nombremozo" value="" placeholder="Nombre" autocomplete="off" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="gastro-descuento-slot-original" class="col-md-12 px-0">
                                <div id="gastro-descuento-en-modal-aviso" class="d-none mb-2">Descuento y cliente interno se cargan en el modal central (F8).</div>
                                <div id="gastro-descuento-movable">
                                    <div class="form-group col-md-12 mb-2">
                                        <label class="small mb-0">Descuento gastronomía</label>
                                        <div class="gastro-campo-consulta">
                                            <input type="text" class="form-control form-control-sm gastro-campo-id descuento_gastronomia_id" id="descuento_gastronomia_id" name="descuento_gastronomia_id" value="" placeholder="ID" autocomplete="off">
                                            <button type="button" title="Consulta descuentos" class="btn-accion-tabla consultadescuento tooltipsC">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="form-control form-control-sm gastro-campo-codigo codigodescuento" id="codigodescuento" name="codigodescuento" value="" placeholder="Código" autocomplete="off">
                                            <input type="text" class="form-control form-control-sm gastro-campo-nombre nombredescuento" id="nombredescuento" name="nombredescuento" value="" placeholder="Nombre" autocomplete="off" readonly>
                                        </div>
                                    </div>
                                    <div id="panel-cliente-descuento" class="form-group col-md-12 mb-2 d-none">
                                        <label class="small mb-0 text-primary">Cliente interno del descuento <span class="text-danger">*</span></label>
                                        <div class="gastro-campo-consulta mt-1">
                                            <input type="text" class="form-control form-control-sm gastro-campo-id cliente_interno_descuento_id" id="cliente_descuento_id" value="" placeholder="ID" autocomplete="off">
                                            <button type="button" title="Consulta cliente interno (invita / centro de costo)" class="btn-accion-tabla consultaclienteinternodescuento tooltipsC">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="form-control form-control-sm gastro-campo-codigo codigoclienteinternodescuento" id="codigocliente_descuento" value="" placeholder="Código" autocomplete="off">
                                            <input type="text" class="form-control form-control-sm gastro-campo-nombre nombreclienteinternodescuento" id="nombrecliente_descuento" value="" placeholder="Nombre / razón social" autocomplete="off" readonly>
                                        </div>
                                        <small class="form-text text-muted">Quien invita o centro de costos donde se carga la invitación. <strong>No</strong> es el cliente de la factura.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <button type="button" class="btn btn-sm btn-primary" id="btn-guardar-cabecera"><i class="fa fa-save"></i> Guardar datos cuenta</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-outline card-success mb-3 gastro-card-consumo-carga">
                    <div class="card-header py-2"><span><i class="fa fa-cutlery"></i> Consumo (catálogo SKU {{ $prefijo_sku }}%)</span></div>
                    <div class="card-body py-2">
                        <p class="small text-muted mb-2 mb-md-1">
                            @if ((int) $sku_catalogo_digitos_sufijo > 0)
                                Ingrese solo los <strong>{{ (int) $sku_catalogo_digitos_sufijo }}</strong> dígitos del código; <kbd>Enter</kbd> agrega cantidad <strong>1</strong> a la cuenta. <kbd>Tab</kbd> busca el artículo y pasa al botón <strong>Agregar</strong> para cargar la cantidad.
                            @else
                                Use la lupa o el SKU; <kbd>Enter</kbd> en el campo SKU agrega con cantidad <strong>1</strong>; <kbd>Tab</kbd> busca y enfoca <strong>Agregar</strong> para ingresar cantidad.
                            @endif
                        </p>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                            <tr id="tr-gastro-linea-articulo">
                                <td class="align-middle py-1" style="white-space:nowrap;">
                                    <input type="hidden" class="articulo_id" id="gastro_linea_articulo_id" value="">
                                    <input type="hidden" class="categoria_id" value="">
                                    <input type="hidden" class="subcategoria_id" value="">
                                    <input type="hidden" class="unidadmedida_id" value="">
                                    <button type="button" title="Consulta artículos (catálogo SKU {{ $prefijo_sku }})" class="btn-accion-tabla consultaarticulo tooltipsC" data-sku-prefijo-filtro="{{ $prefijo_sku }}" data-sku-digitos-filtro="{{ (int) $sku_catalogo_digitos_sufijo }}">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    @if ((int) $sku_catalogo_digitos_sufijo > 0)
                                        <div class="input-group input-group-sm d-inline-flex align-middle" style="width:auto;max-width:200px;vertical-align:middle;">
                                            <div class="input-group-prepend"><span class="input-group-text py-0 px-2">{{ $prefijo_sku }}</span></div>
                                            <input type="text" name="gastro_sku_sufijo" class="form-control gastro-sku-sufijo gastro-carga-sku" maxlength="{{ (int) $sku_catalogo_digitos_sufijo }}" inputmode="numeric" pattern="[0-9]*" placeholder="" autocomplete="off" style="min-width:72px;">
                                            <input type="hidden" class="codigoarticulo" value="">
                                        </div>
                                    @else
                                        <input type="text" class="form-control form-control-sm codigoarticulo gastro-carga-sku d-inline-block align-middle" style="width:118px;vertical-align:middle;" placeholder="SKU" autocomplete="off">
                                    @endif
                                </td>
                                <td class="py-1">
                                    <input type="text" class="form-control form-control-sm descripcionarticulo" placeholder="Descripción" readonly autocomplete="off">
                                </td>
                                <td class="align-middle py-1 text-nowrap">
                                    <button type="button" class="btn btn-sm btn-success" id="btn-agregar-consumo"><i class="fa fa-plus"></i> Agregar</button>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        @if ($wsfe_forzar_modo_caea)
                            <div class="gastro-aviso-caea alert alert-warning py-2" role="status">
                                <strong>Modo CAEA forzado</strong> (<code>ARCA_WSFE_FORZAR_MODO_CAEA=true</code>): las facturas no consultan el web service ARCA en línea.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="card card-outline card-dark mb-3 gastro-card-detalle-cuenta">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span><i class="fa fa-list"></i> Consumos / herramientas</span>
                        <div class="d-flex align-items-center flex-wrap" style="gap: 0.35rem;">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-success" id="tool-facturar" title="Facturar"><i class="fa fa-file-invoice-dollar"></i></button>
                                <button type="button" class="btn btn-outline-info" id="tool-asignar-cliente" title="Enfocar cliente para facturar"><i class="fa fa-user"></i></button>
                                <button type="button" class="btn btn-outline-secondary" id="tool-descuento" title="Enfocar descuento"><i class="fa fa-percent"></i></button>
                                <a href="{{ route('gastronomia_facturas_dia') }}" class="btn btn-outline-primary" title="Facturas del día"><i class="fa fa-calendar-day"></i></a>
                                @if ($requiere_habilitacion_turno ?? true)
                                <a href="{{ route('gastronomia_habilitacion_turno', ['accion' => 'cierre_parcial']) }}" class="btn btn-outline-warning" title="Cierre parcial del turno"><i class="fa fa-list-alt"></i></a>
                                <a href="{{ route('gastronomia_habilitacion_turno', ['accion' => 'cierre_definitivo']) }}" class="btn btn-outline-danger" title="Cierre definitivo del turno"><i class="fa fa-lock"></i></a>
                                @endif
                            </div>
                            <span id="gastro-facturacion-loading" style="display:none; color:#6c757d; font-size:0.95em; white-space:nowrap;">
                                <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                                <span class="gastro-facturacion-loading-text">Facturando…</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body py-2 d-flex flex-column" style="min-height: 420px;">
                        <div id="panel-detalle-lineas" class="gastro-panel-lineas"></div>
                        <div id="panel-cobranza-compacta" class="small mt-2">
                            <input type="hidden" id="gastro-empresa-id" value="{{ (int) $empresa_id }}">
                            <input type="hidden" id="factura-moneda-id" value="">
                            <input type="hidden" id="empresa_id" value="{{ (int) $empresa_id }}">
                            <div class="d-flex justify-content-between align-items-center flex-wrap mb-1" style="gap: 0.35rem;">
                                <strong>Cobranza</strong>
                                <span class="text-muted" style="font-size:11px;">Se graba al facturar · total en $ · <kbd>Enter</kbd> en código y monto</span>
                            </div>
                            <div id="gastro-cobranza-cotiz-bar" class="d-none" role="status" aria-live="polite"></div>
                            <input type="hidden" id="gastro-cotizacion-extranjera" value="">
                            <input type="hidden" id="gastro-moneda-extranjera-id" value="">
                            <div class="table-responsive gastro-cobranza-scroll">
                                <table class="table table-sm table-bordered mb-0 bg-white" id="gastro-cuenta-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th style="width: 42%;">Cuenta de caja</th>
                                            <th style="width: 8%;">Mon.</th>
                                            <th style="width: 18%;">Monto</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-gastro-cuenta-table"></tbody>
                                </table>
                            </div>
                            <div class="mt-1">
                                <button type="button" class="btn btn-sm btn-danger" id="gastro-agrega-renglon-cuenta">+ Agregar renglón</button>
                                <div id="gastro-totales-cobranza" class="gastro-totales-resumen"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.ventas.modalconsultacliente')
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultamozo')
@include('includes.stock.modalconsultadescuento')
@include('includes.caja.modalconsultacuentacaja')

<template id="gastro-template-renglon-cuenta">
    <tr class="item-cuenta-gastro">
        <td>
            <div class="gastro-cc-cuenta-wrap">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas (uso Gastronomía)" class="btn-accion-tabla consultacuentacaja tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm gastro-cc-codigo codigo" value="" placeholder="Cód." autocomplete="off">
                <input type="text" class="form-control form-control-sm gastro-cc-nombre nombre" value="" placeholder="Descripción cuenta" readonly>
            </div>
        </td>
        <td class="gastro-cc-moneda moneda-label">—</td>
        <td>
            <input type="hidden" class="moneda_id" value="">
            <input type="number" step="0.01" class="form-control form-control-sm gastro-cc-monto monto" value="">
        </td>
        <td class="text-center">
            <button type="button" title="Eliminar línea" class="btn-accion-tabla gastro-eliminar-cuenta">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>

<!-- Modal opcionales -->
<div class="modal fade" id="modal-opcionales" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title">Opcionales del artículo</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body py-2" id="modal-opcionales-body"></div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-opcionales-confirmar">Agregar</button>
            </div>
        </div>
    </div>
</div>

<!-- Avisos persistentes (facturación, errores largos) -->
<div class="modal fade" id="modal-gastro-aviso" tabindex="-1" role="dialog" aria-labelledby="modal-gastro-aviso-titulo" data-backdrop="static" data-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" id="modal-gastro-aviso-header">
                <h6 class="modal-title mb-0" id="modal-gastro-aviso-titulo">Aviso</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-3">
                <div class="gastro-aviso-detalle d-none" id="modal-gastro-aviso-detalle"></div>
                <div id="modal-gastro-aviso-mensaje"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-primary btn-sm" id="modal-gastro-aviso-aceptar" data-dismiss="modal">
                    Entendido <small class="text-white-50">(Enter)</small>
                </button>
            </div>
        </div>
    </div>
</div>

<iframe id="gastro-iframe-impresion-factura" title="Impresión factura" aria-hidden="true"></iframe>

<!-- Modal apertura cuenta (cubiertos / mozo) -->
<div class="modal fade" id="modal-abrir-cuenta" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-abrir-cuenta-titulo">Abrir cuenta</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <div class="form-row align-items-end gastro-fila-cubiertos-mozo">
                    <div class="col-auto">
                        <label class="small mb-0 d-block" for="abrir-cubiertos">
                            Cubiertos
                            @if ($cubiertos_obligatorio_al_abrir)
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <input type="number" min="0" class="form-control form-control-sm" id="abrir-cubiertos" value="{{ (int) ($cubiertos_default_al_abrir ?? 1) }}" style="width:72px;" autocomplete="off">
                    </div>
                    <div class="col">
                        <label class="small mb-0 d-block">
                            Mozo
                            @if ($mozo_obligatorio_al_abrir)
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <div id="modal-abrir-cuenta-mozo-wrap" class="gastro-campo-consulta">
                            <input type="text" class="form-control form-control-sm gastro-campo-id mozo_gastronomia_id" id="abrir-mozo_gastronomia_id" value="" placeholder="ID" autocomplete="off">
                            <button type="button" title="Consulta mozos" class="btn-accion-tabla consultamozo tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm gastro-campo-codigo codigomozo" id="abrir-codigomozo" value="" placeholder="Código" autocomplete="off">
                            <input type="text" class="form-control form-control-sm gastro-campo-nombre nombremozo" id="abrir-nombremozo" value="" placeholder="Nombre" autocomplete="off" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-abrir-cuenta-confirmar">Abrir cuenta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal F8: descuento + cliente interno (mismo DOM que la tarjeta; portal desde JS) -->
<div class="modal fade" id="modal-f8-descuento" tabindex="-1" role="dialog" aria-labelledby="modal-f8-descuento-titulo" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="modal-f8-descuento-titulo">Facturar con descuento gastronomía</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-3">
                <p class="small text-muted mb-3">
                    Revise o cargue el descuento y el cliente interno si el descuento lo requiere. Si ya estaban cargados en la cuenta, aparecen aquí igual.
                </p>
                <div id="gastro-descuento-slot-modal"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-f8-descuento-confirmar">Facturar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal cantidad -->
<div class="modal fade" id="modal-cantidad" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title">Cantidad</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button></div>
            <div class="modal-body py-2">
                <input type="number" step="any" min="0.0001" class="form-control" id="fld-cantidad-linea" value="1">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-primary" id="modal-cantidad-confirmar">Continuar</button>
            </div>
        </div>
    </div>
</div>

@if ($requiere_habilitacion_turno ?? true)
    @include('ventas.gastronomia.proceso_facturacion.partials.modales_turno_operativo')
@endif
@endsection
