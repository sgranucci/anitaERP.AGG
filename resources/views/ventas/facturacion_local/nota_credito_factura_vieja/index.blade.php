@extends("theme.$theme.layout")
@section('titulo')
    Notas de crédito Varias
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/pages/scripts/ventas/facturacion_local/pos.css') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/pos.css')) ?: time() }}">
@endsection

@section('scripts')
<script>
window.FL_NC_VIEJA = {
    localId: {{ (int) ($local->id ?? 0) }},
    turnoId: {{ (int) ($turno->id ?? 0) }},
    urls: {
        buscar: @json(route('facturacion_local_api_buscar')),
        variantes: @json(url('ventas/facturacion-local/api/variantes')),
        precio: @json(route('facturacion_local_api_precio')),
        emitir: @json(route('facturacion_local_nc_factura_vieja_emitir')),
    },
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/talle/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/talle/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/color/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/color/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/nc-factura-vieja.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/nc-factura-vieja.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="fl-pos" id="fl-nc-root">
    <div class="fl-top">
        <strong>Notas de crédito Varias</strong>
        @if ($locales->count() === 1 && $local)
            <span class="fl-local-fijo" title="Local asignado">
                <i class="fa fa-map-marker"></i> {{ $local->codigo }} — {{ $local->nombre }}
            </span>
        @else
            <form method="get" action="{{ route('facturacion_local_nc_factura_vieja') }}" class="d-inline">
                <select name="local_id" onchange="this.form.submit()" title="Local">
                    <option value="">Elegir local…</option>
                    @foreach ($locales as $loc)
                        <option value="{{ $loc->id }}" @if ((int) ($local->id ?? 0) === (int) $loc->id) selected @endif>
                            {{ $loc->codigo }} — {{ $loc->nombre }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
        @if ($turno)
            <span class="fl-status ok">Turno abierto #{{ $turno->id }}</span>
        @elseif ($local)
            <span class="fl-status off">Caja cerrada — abrí el turno para emitir</span>
        @endif
        <div class="ml-auto">
            <a href="{{ route('facturacion_local_facturas') }}" class="fl-btn fl-btn-ghost">Comprobantes del día</a>
        </div>
    </div>

    @if ($local)
        <div class="fl-ctx-bar">
            <div class="fl-ctx-item">
                <span class="fl-ctx-lbl">Punto de venta</span>
                <span class="fl-ctx-val">
                    @if (!empty($contexto['puntoventa_codigo']))
                        {{ $contexto['puntoventa_codigo'] }} — {{ $contexto['puntoventa_nombre'] }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="fl-ctx-item">
                <span class="fl-ctx-lbl">Depósito</span>
                <span class="fl-ctx-val">
                    @if (!empty($contexto['deposito_codigo']))
                        {{ $contexto['deposito_codigo'] }} — {{ $contexto['deposito_nombre'] }}
                    @else
                        —
                    @endif
                </span>
            </div>
            <div class="fl-ctx-item">
                <span class="fl-ctx-lbl">Fecha</span>
                <input type="date" id="fl-nc-fecha" class="form-control form-control-sm"
                    value="{{ $fecha }}" min="{{ $fecha_min }}" max="{{ $fecha_max }}"
                    @if (! $turno) disabled @endif
                    style="width:10.5rem;">
            </div>
        </div>
    @endif

    @if (! $local)
        <div class="fl-panel"><p>Elegí un local para armar la nota de crédito.</p></div>
    @else
    <div class="fl-grid">
        <div class="fl-panel">
            <h4>Artículos</h4>
            <div class="fl-search">
                <input type="text" id="fl-q" class="fl-sku-input" placeholder="SKU o descripción — Enter busca" autocomplete="off" autofocus @if (! $turno) disabled @endif>
                <button type="button" class="fl-btn fl-btn-primary" id="fl-buscar" @if (! $turno) disabled @endif>Buscar</button>
            </div>
            <div class="fl-results" id="fl-results"></div>
            <div class="fl-cart">
                <table>
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Artículo</th>
                            <th>Talle / color</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="fl-cart-body"></tbody>
                </table>
            </div>
            <p class="fl-keys">
                Misma carga que el facturador: talle y color (o combinación). El precio sale de la lista del local; si la factura vieja tenía otro, cambialo en la grilla.
            </p>
        </div>
        <div class="fl-panel">
            <h4>Nota de crédito</h4>
            <p class="small text-muted">
                No se asocia a una factura. ARCA recibe el período de este mes.
                El neto va a <strong>ventas de locales</strong>, igual que la factura.
                La contrapartida va a <strong>L-notas de crédito</strong>, la cuenta configurada en Cuentas automáticas.
                El comprobante queda en Facturas Local del día, igual que el resto.
            </p>
            @if (! $turno)
                <p>Abrí la caja desde el POS Local antes de emitir.</p>
                <a href="{{ route('facturacion_local_pos', ['local_id' => $local->id]) }}" class="fl-btn fl-btn-warn">Ir al POS</a>
            @endif
            <div class="fl-totales mt-3" id="fl-totales">$ 0,00</div>
            <div class="mt-3">
                <button type="button" class="fl-btn fl-btn-ok" id="fl-emitir" @if (! $turno) disabled @endif>Emitir nota de crédito</button>
            </div>
            <div id="fl-msg" class="fl-msg mt-2" style="min-height:24px;font-size:14px;"></div>
        </div>
    </div>
    @endif
</div>
<iframe id="fl-iframe-impresion" title="Impresión nota de crédito" aria-hidden="true" style="position:fixed;left:0;top:0;width:0;height:0;border:0;opacity:0;"></iframe>
<div id="fl-overlay"><div class="box"><i class="fa fa-spinner fa-spin"></i> <span id="fl-overlay-txt">Emitiendo…</span></div></div>

<div class="modal fade" id="fl-modal-var" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fl-modal-var-title">Variante</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="fl-var-ayuda">Foco en talle · Enter avanza · Enter en cantidad agrega · F1 consulta</p>
                <div id="fl-var-aviso" class="fl-var-aviso d-none" role="status"></div>
                <div class="form-group tm-talle-campo" id="fl-var-talle-wrap">
                    <label class="d-block">Talle</label>
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
                    <label>Cantidad</label>
                    <input type="number" step="1" min="1" id="fl-var-cant" class="form-control" value="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="fl-var-ok">Agregar</button>
            </div>
        </div>
    </div>
</div>

@include('includes.stock.modalconsultatalle')
@include('includes.stock.modalconsultacolor')
@include('includes.stock.modalconsultacombinacion')
@endsection
