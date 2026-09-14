@extends("theme.$theme.layout")
@section('titulo')
    Facturación Local
@endsection

@section('styles')
<style>
    body.fl-pos-active .main-sidebar,
    body.fl-pos-active .main-header,
    body.fl-pos-active .main-footer { display: none !important; }
    body.fl-pos-active .content-wrapper { margin-left: 0 !important; }
    .fl-pos {
        --fl-bg: #0f1419;
        --fl-panel: #1a2332;
        --fl-accent: #3d9cf0;
        --fl-ok: #2ecc71;
        --fl-warn: #f39c12;
        --fl-text: #e8eef7;
        --fl-muted: #8b9bb4;
        min-height: calc(100vh - 40px);
        background: var(--fl-bg);
        color: var(--fl-text);
        padding: 12px;
        font-family: "Segoe UI", system-ui, sans-serif;
    }
    .fl-pos * { box-sizing: border-box; }
    .fl-top {
        display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
        margin-bottom: 12px; padding: 10px 14px; background: var(--fl-panel); border-radius: 10px;
    }
    .fl-top select, .fl-top input {
        background: #0d121a; border: 1px solid #2a3a50; color: var(--fl-text);
        border-radius: 6px; padding: 8px 10px; min-height: 42px;
    }
    .fl-grid {
        display: grid;
        grid-template-columns: 1.4fr 0.9fr;
        gap: 12px;
        min-height: 70vh;
    }
    @media (max-width: 992px) { .fl-grid { grid-template-columns: 1fr; } }
    .fl-panel {
        background: var(--fl-panel); border-radius: 12px; padding: 14px;
        display: flex; flex-direction: column; gap: 10px;
    }
    .fl-panel h4 { margin: 0; font-size: 15px; letter-spacing: .04em; text-transform: uppercase; color: var(--fl-muted); }
    .fl-search { display: flex; gap: 8px; }
    .fl-search input {
        flex: 1; font-size: 20px; padding: 14px; background: #0d121a;
        border: 2px solid #2a3a50; border-radius: 8px; color: var(--fl-text);
    }
    .fl-search input:focus { border-color: var(--fl-accent); outline: none; }
    .fl-results {
        max-height: 180px; overflow: auto; border: 1px solid #2a3a50; border-radius: 8px;
    }
    .fl-results button {
        display: block; width: 100%; text-align: left; background: transparent;
        border: 0; border-bottom: 1px solid #243146; color: var(--fl-text);
        padding: 10px 12px; cursor: pointer; font-size: 15px;
    }
    .fl-results button:hover { background: #243146; }
    .fl-cart { flex: 1; overflow: auto; min-height: 240px; }
    .fl-cart table { width: 100%; border-collapse: collapse; }
    .fl-cart th {
        background: #243146; color: var(--fl-text); padding: 8px; font-size: 12px; text-align: left;
    }
    .fl-cart td { padding: 8px; border-bottom: 1px solid #243146; font-size: 14px; vertical-align: middle; }
    .fl-cart input {
        width: 80px; background: #0d121a; border: 1px solid #2a3a50; color: var(--fl-text);
        border-radius: 4px; padding: 6px; text-align: right;
    }
    .fl-totales { font-size: 28px; font-weight: 700; text-align: right; color: var(--fl-ok); }
    .fl-totales small { display: block; font-size: 13px; color: var(--fl-muted); font-weight: 400; }
    .fl-btn {
        border: 0; border-radius: 8px; padding: 12px 16px; font-weight: 600; cursor: pointer;
        font-size: 15px;
    }
    .fl-btn-primary { background: var(--fl-accent); color: #fff; }
    .fl-btn-ok { background: var(--fl-ok); color: #0a1a10; }
    .fl-btn-warn { background: var(--fl-warn); color: #1a1200; }
    .fl-btn-ghost { background: #243146; color: var(--fl-text); }
    .fl-btn:disabled { opacity: .45; cursor: not-allowed; }
    .fl-medios .row-medio { display: flex; gap: 8px; margin-bottom: 6px; }
    .fl-medios select, .fl-medios input {
        background: #0d121a; border: 1px solid #2a3a50; color: var(--fl-text);
        border-radius: 6px; padding: 8px; min-height: 40px;
    }
    .fl-badge {
        display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px;
        background: #243146; color: var(--fl-muted);
    }
    .fl-badge.open { background: rgba(46,204,113,.15); color: var(--fl-ok); }
    .fl-badge.closed { background: rgba(243,156,18,.15); color: var(--fl-warn); }
    .fl-keys { font-size: 11px; color: var(--fl-muted); }
    .fl-keys kbd {
        background: #0d121a; border: 1px solid #2a3a50; border-radius: 4px;
        padding: 1px 5px; color: var(--fl-text);
    }
    #fl-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,.55);
        z-index: 2050; align-items: center; justify-content: center;
    }
    #fl-overlay.show { display: flex; }
    #fl-overlay .box {
        background: #ffc107; color: #1a1200; padding: 18px 24px; border-radius: 10px;
        font-weight: 600; min-width: 220px; text-align: center;
    }
</style>
@endsection

@section('scripts')
<script>
window.FL_POS = {
    localId: {{ (int) ($local->id ?? 0) }},
    turnoId: {{ (int) ($turno->id ?? 0) }},
    turnosMaestro: @json($turnosMaestro ?? []),
    cuentas: @json($cuentasPos ?? []),
    efectivoId: {{ (int) ($local->cuentacaja_efectivo_id ?? 0) }},
    urls: {
        buscar: @json(url('ventas/facturacion-local/api/buscar-articulo')),
        variantes: @json(url('ventas/facturacion-local/api/variantes')),
        precio: @json(url('ventas/facturacion-local/api/precio')),
        emitir: @json(url('ventas/facturacion-local/api/emitir')),
        preview: @json(url('ventas/facturacion-local/api/preview-totales')),
        cliente: @json(url('ventas/facturacion-local/api/cliente')),
        vales: @json(url('ventas/facturacion-local/api/vales')),
        abrirTurno: @json(route('facturacion_local_turno_abrir')),
        cerrarTurno: @json($turno ? route('facturacion_local_turno_cerrar', $turno->id) : ''),
    },
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/pos.js') }}"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="fl-pos" id="fl-pos-root">
    <div class="fl-top">
        <strong style="font-size:18px;">Facturación Local</strong>
        <form method="get" action="{{ route('facturacion_local_pos') }}" class="d-inline">
            <select name="local_id" onchange="this.form.submit()">
                <option value="">Local…</option>
                @foreach ($locales as $loc)
                    <option value="{{ $loc->id }}" @if ((int) ($local->id ?? 0) === (int) $loc->id) selected @endif>
                        {{ $loc->codigo }} — {{ $loc->nombre }}
                    </option>
                @endforeach
            </select>
        </form>
        @if ($turno)
            <span class="fl-badge open">
                Turno #{{ $turno->id }}
                @if ($turno->turnoLocal)
                    · {{ $turno->turnoLocal->nombre }}
                @endif
                abierto
            </span>
        @else
            <span class="fl-badge closed">Sin turno</span>
        @endif
        <div class="ml-auto d-flex gap-2 align-items-center" style="gap:8px;">
            @if ($local && ! $turno && can('abrir-turno-facturacion-local', false))
                <select id="fl-turno-local-id" class="form-control form-control-sm" style="width:auto;min-width:180px;">
                    <option value="">Turno…</option>
                    @foreach ($turnosMaestro ?? [] as $tm)
                        <option value="{{ $tm['id'] }}">{{ $tm['etiqueta'] }}</option>
                    @endforeach
                </select>
                <button type="button" class="fl-btn fl-btn-warn" id="fl-abrir-turno">Abrir turno</button>
            @endif
            @if ($turno && can('cerrar-turno-facturacion-local', false))
                <button type="button" class="fl-btn fl-btn-ghost" id="fl-cerrar-turno">Cerrar turno</button>
            @endif
            <a href="{{ route('facturacion_local_turno') }}" class="fl-btn fl-btn-ghost">Turnos</a>
            <a href="{{ route('facturacion_local_locales') }}" class="fl-btn fl-btn-ghost">Locales</a>
        </div>
    </div>

    @if (! $local)
        <div class="fl-panel"><p>Seleccione un local para operar.</p></div>
    @else
    <div class="fl-grid">
        <div class="fl-panel">
            <h4>Carrito</h4>
            <div class="fl-search">
                <input type="text" id="fl-q" placeholder="SKU / descripción — Enter busca · F1 limpia" autocomplete="off" @if (! $turno) disabled @endif>
                <button type="button" class="fl-btn fl-btn-primary" id="fl-buscar" @if (! $turno) disabled @endif>Buscar</button>
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
                Cantidad negativa = devolución (genera NC).
                <kbd>F2</kbd> cobrar · <kbd>F8</kbd> ticket regalo · <kbd>Esc</kbd> limpia búsqueda
            </p>
        </div>
        <div class="fl-panel">
            <h4>Cliente / Cobranza</h4>
            <div class="form-group mb-2">
                <input type="text" id="fl-cliente-codigo" class="form-control" placeholder="Código / DNI / CUIT cliente" @if (! $turno) disabled @endif>
                <input type="hidden" id="fl-cliente-id" value="">
                <div id="fl-cliente-nombre" class="mt-1" style="color:#8b9bb4;font-size:13px;">Consumidor final</div>
            </div>
            <div class="fl-medios" id="fl-medios"></div>
            <button type="button" class="fl-btn fl-btn-ghost" id="fl-add-medio" @if (! $turno) disabled @endif>+ Medio</button>
            <div class="mt-2">
                <label style="font-size:12px;color:#8b9bb4;">Si hay excedente / NC mayor</label>
                <select id="fl-excedente" class="form-control">
                    <option value="">—</option>
                    <option value="vale">Generar vale a cuenta</option>
                    <option value="reintegro">Reintegro (sin vale)</option>
                </select>
            </div>
            <div class="mt-3 d-flex flex-column" style="gap:8px;">
                <button type="button" class="fl-btn fl-btn-ok" id="fl-emitir" @if (! $turno) disabled @endif>Emitir (F2)</button>
                <button type="button" class="fl-btn fl-btn-warn" id="fl-regalo" @if (! $turno) disabled @endif>Ticket regalo (F8)</button>
            </div>
            <div id="fl-msg" class="mt-2" style="min-height:24px;font-size:14px;"></div>
        </div>
    </div>
    @endif
</div>
<div id="fl-overlay"><div class="box"><i class="fa fa-spinner fa-spin"></i> <span id="fl-overlay-txt">Procesando…</span></div></div>

{{-- Modal variantes --}}
<div class="modal fade" id="fl-modal-var" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#1a2332;color:#e8eef7;">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="fl-modal-var-title">Variante</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Talle (obligatorio)</label>
                    <select id="fl-var-talle" class="form-control"></select>
                </div>
                <div class="form-group" id="fl-var-color-wrap">
                    <label>Color</label>
                    <select id="fl-var-color" class="form-control"></select>
                </div>
                <div class="form-group" id="fl-var-comb-wrap">
                    <label>Combinación</label>
                    <select id="fl-var-comb" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label>Cantidad (negativa = devolución)</label>
                    <input type="number" step="1" id="fl-var-cant" class="form-control" value="1">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="fl-btn fl-btn-primary" id="fl-var-ok">Agregar</button>
            </div>
        </div>
    </div>
</div>
@endsection
