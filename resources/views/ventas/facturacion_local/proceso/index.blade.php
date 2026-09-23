@extends("theme.$theme.layout")
@section('titulo')
    Facturación Local
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/pages/scripts/ventas/facturacion_local/pos.css') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/pos.css')) ?: time() }}">
@endsection

@section('scripts')
<script>
window.FL_POS = {
    localId: {{ (int) ($local->id ?? 0) }},
    turnoId: {{ (int) ($turno->id ?? 0) }},
    turnoSugeridoId: {{ (int) ($turnoSugeridoId ?? 0) }},
    turnosMaestro: @json($turnosMaestro ?? []),
    cuentas: @json($cuentasPos ?? []),
    efectivoId: {{ (int) ($local->cuentacaja_efectivo_id ?? 0) }},
    empresaId: {{ (int) ($empresaIdPos ?? 0) }},
    contexto: @json($contextoPos ?? []),
    urls: {
        pos: @json(route('facturacion_local_pos')),
        buscar: @json(url('ventas/facturacion-local/api/buscar-articulo')),
        variantes: @json(url('ventas/facturacion-local/api/variantes')),
        precio: @json(url('ventas/facturacion-local/api/precio')),
        emitir: @json(url('ventas/facturacion-local/api/emitir')),
        preview: @json(url('ventas/facturacion-local/api/preview-totales')),
        cliente: @json(url('ventas/facturacion-local/api/cliente')),
        vales: @json(url('ventas/facturacion-local/api/vales')),
        contextoPos: @json(route('facturacion_local_api_contexto_pos')),
        consultaStockPrecios: @json(route('facturacion_local_api_consulta_stock_precios')),
        abrirTurno: @json(route('facturacion_local_turno_abrir')),
        cerrarTurno: @json($turno ? route('facturacion_local_turno_cerrar', $turno->id) : ''),
    },
    csrf: @json(csrf_token()),
};
window.FACTURACION_LOCAL = {
    usocuentacajaLocalId: {{ (int) ($usocuentacajaLocalId ?? 0) }},
    empresaId: {{ (int) ($empresaIdPos ?? 0) }},
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/talle/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/talle/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/color/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/color/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/localidad/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/localidad/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/provincia/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/provincia/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/pos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/pos.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<input type="hidden" id="empresa_id" value="{{ (int) ($empresaIdPos ?? 0) }}">
<div class="fl-pos" id="fl-pos-root">
    <div class="fl-top">
        <strong>POS Local</strong>
        <form method="get" action="{{ route('facturacion_local_pos') }}" class="d-inline">
            <select name="local_id" onchange="this.form.submit()" title="Local">
                <option value="">Elegir local…</option>
                @foreach ($locales as $loc)
                    <option value="{{ $loc->id }}" @if ((int) ($local->id ?? 0) === (int) $loc->id) selected @endif>
                        {{ $loc->codigo }} — {{ $loc->nombre }}
                    </option>
                @endforeach
            </select>
        </form>
        @if ($turno)
            <span class="fl-status ok">
                Caja abierta
                · {{ $turno->turnoLocal->nombre ?? 'Turno' }}
                · #{{ $turno->id }}
                · {{ optional($turno->apertura_en)->format('H:i') }}
                @if ($turno->usuarioApertura)
                    · {{ $turno->usuarioApertura->nombre }}
                @endif
            </span>
        @elseif ($local)
            <span class="fl-status off">Caja cerrada — hay que abrir el turno para cobrar</span>
        @endif
        <div class="ml-auto d-flex align-items-center" style="gap:8px;">
            @if ($local)
                <button type="button" class="fl-btn fl-btn-ghost" id="fl-tool-stock" title="Consultar stock y precios (F3)">
                    <i class="fa fa-cubes"></i> Stock / precios
                </button>
            @endif
            @if ($turno && can('cerrar-turno-facturacion-local', false))
                <a href="{{ route('facturacion_local_turno_ver', $turno->id) }}" class="fl-btn fl-btn-ghost">Cerrar turno</a>
            @endif
            <a href="{{ route('facturacion_local_turno') }}" class="fl-btn fl-btn-ghost">Turnos</a>
            <a href="{{ route('facturacion_local_locales') }}" class="fl-btn fl-btn-ghost">Locales</a>
        </div>
    </div>

    @if ($local)
        @php $ctx = $contextoPos ?? []; @endphp
        <div class="fl-ctx-bar" id="fl-ctx-bar" title="{{ $ctx['aviso'] ?? 'Contexto de emisión del local (informativo)' }}">
            <div class="fl-ctx-item">
                <span class="fl-ctx-lbl">Punto de venta</span>
                <span class="fl-ctx-val" id="fl-ctx-pv">
                    @if (!empty($ctx['puntoventa_codigo']))
                        {{ $ctx['puntoventa_codigo'] }} — {{ $ctx['puntoventa_nombre'] }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="fl-ctx-item">
                <span class="fl-ctx-lbl">Depósito</span>
                <span class="fl-ctx-val" id="fl-ctx-dep">
                    @if (!empty($ctx['deposito_codigo']))
                        {{ $ctx['deposito_codigo'] }} — {{ $ctx['deposito_nombre'] }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="fl-ctx-item fl-ctx-prox" id="fl-proximo-comprobante" title="Próximo número: con webservice consulta ARCA; al emitir se confirma">
                <span class="fl-ctx-lbl">Próxima factura</span>
                <span class="fl-ctx-val" id="fl-ctx-prox-val">{{ $ctx['proxima_etiqueta'] ?? '—' }}</span>
            </div>
            @if (!empty($ctx['listaprecio_codigo']))
                <div class="fl-ctx-item">
                    <span class="fl-ctx-lbl">Lista</span>
                    <span class="fl-ctx-val" id="fl-ctx-lista">{{ $ctx['listaprecio_codigo'] }} — {{ $ctx['listaprecio_nombre'] }}</span>
                </div>
            @endif
        </div>
    @endif

    @if (! $local)
        <div class="fl-panel"><p>Elegí un local arriba para operar.</p></div>
    @else
    <div class="fl-grid">
        <div class="fl-panel">
            @if (! $turno)
                <div class="fl-gate" id="fl-gate">
                    <p>
                        El POS de <strong>{{ $local->nombre }}</strong> no tiene turno abierto.
                        Elegí el turno (se sugiere el de ahora) y abrí la caja. Recién ahí se puede cobrar.
                    </p>
                    @if (can('abrir-turno-facturacion-local', false))
                        <div class="fl-gate-row">
                            <select id="fl-turno-local-id">
                                <option value="">Turno…</option>
                                @foreach ($turnosMaestro ?? [] as $tm)
                                    <option value="{{ $tm['id'] }}" @if ((int) ($turnoSugeridoId ?? 0) === (int) $tm['id']) selected @endif>
                                        {{ $tm['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="number" id="fl-fondo-inicial" step="0.01" min="0" value="0" title="Fondo inicial" placeholder="Fondo">
                            <button type="button" class="fl-btn fl-btn-warn" id="fl-abrir-turno">Abrir caja</button>
                        </div>
                    @else
                        <p>No tenés permiso para abrir turno.</p>
                    @endif
                </div>
            @endif
            <h4>Artículos</h4>
            <div class="fl-search">
                <button type="button" class="fl-btn fl-btn-ghost" id="fl-q-lupa" title="Consulta artículos (F1)">
                    <i class="fa fa-search"></i>
                </button>
                <input type="text" id="fl-q" class="fl-sku-input" placeholder="SKU o descripción — F1 consulta · Enter busca" autocomplete="off" autofocus>
                <button type="button" class="fl-btn fl-btn-primary" id="fl-buscar">Buscar</button>
            </div>
            <div class="fl-results" id="fl-results"></div>
            <div class="fl-cart">
                <table>
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th>Var.</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Dto%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="fl-cart-body"></tbody>
                </table>
            </div>
            <div class="fl-totales" id="fl-totales">
                $ 0,00
                <small id="fl-totales-detalle">FAC 0 · NC 0</small>
            </div>
            <p class="fl-keys">
                Cantidad negativa = devolución (NC). Neto 0 → $0,01 ARCA. Neto negativo → NC completa afuera.
                <kbd>F1</kbd> consulta art. · <kbd>F2</kbd> cobrar · <kbd>F3</kbd> stock/precios · <kbd>F8</kbd> ticket regalo · <kbd>Esc</kbd> limpia
            </p>
        </div>
        <div class="fl-panel">
            <h4>Cliente / Cobranza</h4>
            <div class="form-group mb-2">
                <label class="small mb-1 d-block">Cliente <span class="text-muted">(vacío = Consumidor Final / B-C)</span></label>
                <div class="gastro-campo-consulta fl-cliente-campo">
                    <input type="text" class="form-control form-control-sm" id="cliente_id" name="cliente_id" value="" placeholder="ID" autocomplete="off" @if (! $turno) disabled @endif>
                    <button type="button" title="Consulta clientes (F1)" class="btn-accion-tabla consultacliente tooltipsC" @if (! $turno) disabled @endif>
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" class="form-control form-control-sm codigocliente" id="codigocliente" name="codigocliente" value="" placeholder="Código" autocomplete="off" @if (! $turno) disabled @endif>
                    <input type="text" class="form-control form-control-sm" id="nombrecliente" name="nombrecliente" value="" placeholder="Nombre / razón social" autocomplete="off" readonly>
                </div>
                <div class="fl-letra-row mt-1">
                    <span id="fl-letra-badge" class="badge badge-secondary">Letra B/C · CF</span>
                    <span id="fl-cliente-extra" class="text-muted small ml-1"></span>
                </div>
                <div class="custom-control custom-checkbox mt-2">
                    <input type="checkbox" class="custom-control-input" id="fl-receptor-manual-toggle" @if (! $turno) disabled @endif>
                    <label class="custom-control-label small" for="fl-receptor-manual-toggle">
                        Receptor eventual (no crea cliente; solo esta venta)
                    </label>
                </div>
                <div id="fl-receptor-manual" class="fl-receptor-manual d-none mt-2">
                    <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Tipo de factura eventual">
                        <button type="button" class="btn btn-outline-info active" id="fl-rec-modo-b" data-modo="b">Factura B/C</button>
                        <button type="button" class="btn btn-outline-danger" id="fl-rec-modo-a" data-modo="a">Factura A</button>
                    </div>
                    <div id="fl-rec-campos-b" class="fl-rec-campos">
                        <input type="text" class="form-control form-control-sm mb-1" id="fl-rec-nombre-b" placeholder="Nombre y apellido" autocomplete="off">
                        <input type="text" class="form-control form-control-sm mb-1" id="fl-rec-dni" placeholder="DNI" inputmode="numeric" autocomplete="off">
                        <input type="text" class="form-control form-control-sm" id="fl-rec-domicilio-b" placeholder="Domicilio (opcional)" autocomplete="off">
                    </div>
                    <div id="fl-rec-campos-a" class="fl-rec-campos d-none">
                        <input type="text" class="form-control form-control-sm mb-1" id="fl-rec-nombre-a" placeholder="Razón social / nombre" autocomplete="off">
                        <input type="text" class="form-control form-control-sm mb-1" id="fl-rec-cuit" placeholder="CUIT (11 dígitos)" inputmode="numeric" autocomplete="off">
                        <input type="text" class="form-control form-control-sm mb-1" id="fl-rec-domicilio-a" placeholder="Domicilio" autocomplete="off">
                        <label class="small mb-0 mt-1 d-block">Localidad</label>
                        @include('configuracion.partials.campo_consulta_localidad', [
                            'layout' => 'inline',
                            'inputName' => 'fl_rec_localidad_id',
                            'inputId' => 'fl_rec_localidad_id',
                            'codigoId' => 'fl_rec_codigolocalidad',
                            'nombreId' => 'fl_rec_nombrelocalidad',
                            'codigoName' => 'fl_rec_codigolocalidad',
                            'nombreName' => 'fl_rec_nombrelocalidad',
                            'previaName' => 'fl_rec_localidad_id_previa',
                            'descName' => 'fl_rec_desc_localidad',
                            'extra_class' => 'fl-rec-localidad mb-1',
                            'provinciaSource' => '.fl-rec-provincia .provincia_id',
                        ])
                        <label class="small mb-0 mt-1 d-block">Provincia</label>
                        @include('configuracion.partials.campo_consulta_provincia', [
                            'layout' => 'inline',
                            'inputName' => 'fl_rec_provincia_id',
                            'inputId' => 'fl_rec_provincia_id',
                            'codigoId' => 'fl_rec_codigoprovincia',
                            'nombreId' => 'fl_rec_nombreprovincia',
                            'codigoName' => 'fl_rec_codigoprovincia',
                            'nombreName' => 'fl_rec_nombreprovincia',
                            'extra_class' => 'fl-rec-provincia',
                            'mostrar_jurisdiccion' => false,
                        ])
                    </div>
                    <small class="form-text text-muted">Los datos quedan solo en el comprobante; no se graban en el padrón de clientes.</small>
                </div>
            </div>

            <div class="table-responsive fl-cobranza-scroll">
                <table class="table table-sm table-bordered mb-0 bg-white" id="fl-cuenta-table">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:42%;">Cuenta de caja</th>
                            <th style="width:22%;">Monto</th>
                            <th style="width:21%;">Cupón</th>
                            <th style="width:15%;"></th>
                        </tr>
                    </thead>
                    <tbody id="tbody-fl-cuenta-table"></tbody>
                </table>
            </div>
            <div class="mt-1 fl-cobranza-acciones">
                <button type="button" class="btn btn-sm btn-outline-danger" id="fl-add-medio" @if (! $turno) disabled @endif>+ Renglón</button>
                <div id="fl-medios-rapidos" role="group" aria-label="Medios de pago rápidos"></div>
            </div>

            <div class="mt-2">
                <label style="font-size:12px;color:#5d6d7e;">Si hay excedente de medios</label>
                <select id="fl-excedente" class="form-control" @if (! $turno) disabled @endif>
                    <option value="">—</option>
                    <option value="vale">Generar vale a cuenta</option>
                    <option value="reintegro">Reintegro (sin vale)</option>
                </select>
                <small class="text-muted d-block mt-1">
                    Saldo negativo (devoluciones &gt; ventas): no permitido. Emita NC completa desde Facturas Local.
                    Cambio equivalente (neto 0): se factura $0,01 (ARCA).
                </small>
            </div>
            <div class="mt-3 d-flex flex-column" style="gap:8px;">
                <button type="button" class="fl-btn fl-btn-ok" id="fl-emitir" @if (! $turno) disabled @endif>Cobrar (F2)</button>
                <button type="button" class="fl-btn fl-btn-warn" id="fl-regalo" @if (! $turno) disabled @endif>Ticket regalo (F8)</button>
            </div>
            <div id="fl-msg" class="fl-msg mt-2" style="min-height:24px;font-size:14px;"></div>
        </div>
    </div>
    @endif
</div>
<iframe id="fl-iframe-impresion" title="Impresión factura" aria-hidden="true" style="position:fixed;left:0;top:0;width:0;height:0;border:0;opacity:0;"></iframe>
<div id="fl-overlay"><div class="box"><i class="fa fa-spinner fa-spin"></i> <span id="fl-overlay-txt">Procesando…</span></div></div>

<template id="fl-template-renglon-cuenta">
    <tr class="item-cuenta-fl">
        <td>
            <div class="fl-cc-cuenta-wrap">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas (uso Local)" class="btn-accion-tabla consultacuentacaja tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm fl-cc-codigo codigo codigocuentacaja" value="" placeholder="Cód." autocomplete="off">
                <input type="text" class="form-control form-control-sm fl-cc-nombre nombre" value="" placeholder="Descripción cuenta" readonly>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm fl-cc-monto monto" value="">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm fl-cc-cupon numerocupon d-none" value="" placeholder="Nº cupón" autocomplete="off" inputmode="numeric">
        </td>
        <td class="text-center">
            <button type="button" title="Eliminar línea" class="btn-accion-tabla fl-eliminar-cuenta">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>

<div class="modal fade" id="fl-modal-var" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fl-modal-var-title">Variante</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="fl-var-ayuda">Foco en talle · <kbd>Enter</kbd> avanza al siguiente campo · <kbd>Enter</kbd> en cantidad agrega · <kbd>F1</kbd> consulta</p>
                <div id="fl-var-aviso" class="fl-var-aviso d-none" role="status"></div>
                <div class="form-group tm-talle-campo" id="fl-var-talle-wrap">
                    <label class="d-block">Talle (obligatorio)</label>
                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                        <input type="hidden" class="talle_id" id="fl-var-talle-id" value="">
                        <button type="button" title="Consulta talles (F1)" class="btn-accion-tabla consultatalle">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm codigotalle" id="fl-var-talle-codigo" placeholder="Cód." autocomplete="off" style="width:5.5rem;">
                        <input type="text" class="form-control form-control-sm descripciontalle" id="fl-var-talle-nombre" placeholder="Descripción" readonly>
                    </div>
                </div>
                <div class="form-group tm-color-campo" id="fl-var-color-wrap">
                    <label class="d-block">Color</label>
                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                        <input type="hidden" class="color_id" id="fl-var-color-id" value="">
                        <button type="button" title="Consulta colores (F1)" class="btn-accion-tabla consultacolor">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm codigocolor" id="fl-var-color-codigo" placeholder="Cód." autocomplete="off" style="width:5.5rem;">
                        <input type="text" class="form-control form-control-sm descripcioncolor" id="fl-var-color-nombre" placeholder="Descripción" readonly>
                    </div>
                </div>
                <div class="form-group tm-combinacion-campo" id="fl-var-comb-wrap">
                    <label class="d-block">Combinación</label>
                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                        <input type="hidden" class="combinacion_id" id="fl-var-comb-id" value="">
                        <button type="button" title="Consulta combinaciones (F1)" class="btn-accion-tabla consultacombinacion">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm codigocombinacion" id="fl-var-comb-codigo" placeholder="Cód." autocomplete="off" style="width:5.5rem;">
                        <input type="text" class="form-control form-control-sm descripcioncombinacion" id="fl-var-comb-nombre" placeholder="Descripción" readonly>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label>Cantidad (negativa = devolución)</label>
                    <input type="number" step="1" id="fl-var-cant" class="form-control" value="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="fl-var-ok">Agregar al carrito</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fl-modal-stock" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consulta stock / precios</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-end" style="gap:8px;">
                    <div class="flex-grow-1" style="min-width:12rem;">
                        <label class="small mb-1 d-block" for="fl-stock-q">SKU o descripción</label>
                        <input type="text" id="fl-stock-q" class="form-control form-control-sm" placeholder="Ej. MELISA o 41079206" autocomplete="off">
                    </div>
                    <div class="form-check mb-1">
                        <input type="checkbox" class="form-check-input" id="fl-stock-origen-erp" value="1">
                        <label class="form-check-label small" for="fl-stock-origen-erp">Stock ERP</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="fl-stock-buscar">Consultar</button>
                </div>
                <div id="fl-stock-matches" class="fl-stock-matches d-none mt-2"></div>
                <div id="fl-stock-msg" class="small text-muted mt-2"></div>
                <div id="fl-stock-resumen" class="fl-stock-resumen d-none mt-2"></div>
                <div id="fl-stock-combos" class="fl-stock-combos d-none mt-2"></div>
                <div class="table-responsive mt-2 fl-stock-matriz-wrap">
                    <table class="table table-sm table-bordered mb-0 fl-stock-matriz" id="fl-stock-tabla">
                        <thead id="fl-stock-thead" style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Dp</th>
                                <th>Combinación</th>
                                <th class="text-right">Tot</th>
                            </tr>
                        </thead>
                        <tbody id="fl-stock-body">
                            <tr><td colspan="3" class="text-muted text-center">Ingresá un SKU o nombre y consultá.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')
@include('includes.ventas.modalconsultacliente')
@include('includes.stock.modalconsultatalle')
@include('includes.stock.modalconsultacolor')
@include('includes.stock.modalconsultacombinacion')
@include('includes.configuracion.modalconsultalocalidad')
@include('includes.configuracion.modalconsultaprovincia')
@endsection
