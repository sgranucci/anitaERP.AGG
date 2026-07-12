@extends("theme.$theme.layout")

@section('titulo')
    Proceso de viandas
@endsection

@section('styles')
<style>
    #vianda-shell.vianda-bloqueado { filter: blur(2px); pointer-events: none; user-select: none; }
    #modal-vianda-login .modal-dialog { max-width: 420px; }
    #vianda-login-clave.vianda-clave-mask { -webkit-text-security: disc; text-security: disc; }
    .vianda-columnas { align-items: stretch; }
    .vianda-columnas > [class*="col-"] { display: flex; flex-direction: column; }
    .vianda-card-menu, .vianda-card-comanda { flex: 1 1 auto; }
    .vianda-panel-lineas { max-height: 46vh; overflow-y: auto; }
    .vianda-empleado-bar {
        border-left: 4px solid #17a2b8;
        background: linear-gradient(90deg, #d1ecf1 0%, #f6fcfd 100%);
        padding: 0.4rem 0.75rem;
        margin-bottom: 0.5rem;
    }
    .vianda-grupo-titulo {
        font-size: 0.78rem; font-weight: 700; color: #17a2b8;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin: 0.6rem 0 0.4rem; border-bottom: 1px solid #e3e6ea; padding-bottom: 0.2rem;
    }
    .vianda-grilla-menu {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 0.6rem;
    }
    .vianda-tarjeta {
        border: 2px solid #dee2e6; border-radius: 0.5rem; background: #fff;
        padding: 0.55rem; cursor: pointer; text-align: center;
        transition: border-color 0.12s ease, box-shadow 0.12s ease, transform 0.08s ease;
        display: flex; flex-direction: column; align-items: center; min-height: 120px;
    }
    .vianda-tarjeta:hover { border-color: #17a2b8; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(23,162,184,0.15); }
    .vianda-tarjeta:active { transform: translateY(0); }
    .vianda-tarjeta .vianda-ico {
        width: 68px; height: 68px; border-radius: 0.5rem; object-fit: cover;
        background: #eef2f5; display: flex; align-items: center; justify-content: center;
        margin-bottom: 0.35rem; color: #adb5bd; font-size: 1.8rem;
    }
    .vianda-tarjeta .vianda-desc { font-size: 0.82rem; font-weight: 600; color: #212529; line-height: 1.15; }
    .vianda-tarjeta .vianda-sku { font-size: 0.7rem; color: #6c757d; }
    #vianda-shell.vianda-pedido-bloqueado .vianda-tarjeta {
        pointer-events: none; opacity: 0.55; cursor: not-allowed;
    }
    #vianda-shell.vianda-pedido-bloqueado #vianda-btn-limpiar,
    #vianda-shell.vianda-pedido-bloqueado #vianda-observacion {
        pointer-events: none; opacity: 0.65;
    }
    #modal-vianda-voucher pre {
        background: #f8f9fa; border: 1px dashed #ced4da; border-radius: 0.35rem;
        padding: 0.6rem; font-size: 0.78rem; white-space: pre-wrap; max-height: 55vh; overflow-y: auto;
    }
    .vianda-qty-input { width: 64px; text-align: center; }
</style>
@endsection

@section('contenido')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
    <span class="text-muted small">
        Proceso de viandas
        @if ($tiene_cfg && $empresa_nombre)
            — Terminal: <strong>{{ $terminal_nombre ?: $identificador_pc_actual }}</strong>
            · Empresa: <strong>{{ $empresa_nombre }}</strong>
            @if ($terminal_ubicacion) · <span>{{ $terminal_ubicacion }}</span> @endif
        @endif
    </span>
    <a href="{{ route('inicio') }}" class="btn btn-sm btn-outline-secondary" title="Volver al menú principal">
        <i class="fa fa-sign-out"></i> Salir
    </a>
</div>

@if (! $tiene_cfg)
    <div class="alert alert-danger">
        No hay terminal de viandas configurada para este equipo (<code>{{ $identificador_pc_actual }}</code>).
        @if (can('crear-configuracion-terminal-vianda', false))
            Configúrela en <a href="{{ route('consultar_configuracion_terminal_vianda') }}">Terminales de viandas</a>.
        @else
            Pida a un supervisor que la configure.
        @endif
    </div>
@else
    <div id="vianda-jornada-alerta" class="alert alert-warning d-none" role="alert"></div>
    <div id="vianda-pedido-diario-alerta" class="alert alert-info d-none" role="alert"></div>

    <div id="vianda-shell" class="vianda-bloqueado">
        <div class="vianda-empleado-bar d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <span class="badge badge-secondary" id="vianda-empleado-badge">Sin empleado</span>
                <span class="ml-2 text-muted small" id="vianda-empleado-detalle"></span>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="vianda-btn-cambiar-empleado" title="Cambiar empleado">
                    <i class="fa fa-user"></i> Cambiar empleado
                </button>
            </div>
        </div>

        <div class="row vianda-columnas">
            <div class="col-xl-7 mb-3">
                <div class="card card-outline card-info mb-0 vianda-card-menu">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                        <span><i class="fa fa-utensils"></i> Menú del día — <span id="vianda-dia-label">—</span></span>
                        <span class="text-muted small" id="vianda-tipomenu-label"></span>
                    </div>
                    <div class="card-body py-2" id="vianda-menu-contenedor">
                        <p class="text-muted text-center my-4">Identifíquese para ver el menú del día.</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-5 mb-3">
                <div class="card card-outline card-success mb-0 vianda-card-comanda">
                    <div class="card-header py-2"><span><i class="fa fa-list"></i> Comanda a marchar</span></div>
                    <div class="card-body p-2 vianda-panel-lineas">
                        <table class="table table-sm table-striped mb-0" id="vianda-tabla-lineas">
                            <thead><tr><th>Descripción</th><th class="text-center" style="width:120px;">Cant.</th><th style="width:40px;"></th></tr></thead>
                            <tbody id="vianda-tbody-lineas"><tr><td colspan="3" class="text-muted text-center">Sin ítems</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-body py-2 border-top">
                        <label class="small text-muted mb-1" for="vianda-observacion">Observación de comanda</label>
                        <textarea class="form-control form-control-sm" id="vianda-observacion" rows="2" maxlength="2000" placeholder="Indicaciones generales para la cocina"></textarea>
                    </div>
                    <div class="card-footer py-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><span id="vianda-total-items">0</span> ítem(s)</span>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="vianda-btn-limpiar" title="Vaciar comanda"><i class="fa fa-trash"></i> Vaciar</button>
                            <button type="button" class="btn btn-success" id="vianda-btn-marchar" title="Marchar comanda y emitir voucher"><i class="fa fa-check"></i> Marchar comanda</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Login empleado de vianda --}}
<div class="modal fade" id="modal-vianda-login" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-info">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title mb-0"><i class="fa fa-user-circle mr-1"></i> Ingreso empleado — viandas</h5>
            </div>
            <div class="modal-body">
                @if ($tiene_cfg && $empresa_nombre)
                    <div class="alert alert-info py-2 mb-3 small">
                        Terminal: <strong>{{ $terminal_nombre ?: $identificador_pc_actual }}</strong> · Empresa: <strong>{{ $empresa_nombre }}</strong>
                    </div>
                @endif
                <p class="small text-muted mb-3">Ingrese su <strong>código de usuario</strong> y <strong>clave</strong> para cargar su vianda del día.</p>
                <form id="vianda-login-form" autocomplete="off" onsubmit="return false;">
                    <div class="form-group mb-2">
                        <label class="mb-1" for="vianda-login-codigo">Código de usuario</label>
                        <input type="text" class="form-control" id="vianda-login-codigo" autocomplete="off" autofocus>
                    </div>
                    <div class="form-group mb-0">
                        <label class="mb-1" for="vianda-login-clave">Clave</label>
                        <input type="text" class="form-control vianda-clave-mask" id="vianda-login-clave" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
                    </div>
                </form>
                <div class="alert alert-danger d-none mt-2 mb-0 py-2" id="vianda-login-error"></div>
            </div>
            <div class="modal-footer py-2 d-flex flex-wrap justify-content-between">
                <a href="{{ route('inicio') }}" class="btn btn-outline-secondary"><i class="fa fa-sign-out"></i> Salir al menú</a>
                <button type="button" class="btn btn-info" id="vianda-login-confirmar">Ingresar</button>
            </div>
        </div>
    </div>
</div>

{{-- Comentario por ítem --}}
<div class="modal fade" id="modal-vianda-comentario" tabindex="-1">
    <div class="modal-dialog modal-sm"><div class="modal-content">
        <div class="modal-header py-2"><h6 class="modal-title mb-0">Comentario del ítem</h6><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body py-2">
            <p class="small text-muted mb-2" id="vianda-comentario-articulo"></p>
            <textarea class="form-control form-control-sm" id="vianda-comentario-texto" rows="3" maxlength="255" placeholder="Ej. sin sal, sin tacc…"></textarea>
        </div>
        <div class="modal-footer py-2">
            <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="button" class="btn btn-sm btn-primary" id="vianda-comentario-guardar">Guardar</button>
        </div>
    </div></div>
</div>

{{-- Voucher emitido --}}
<div class="modal fade" id="modal-vianda-voucher" tabindex="-1" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header py-2 bg-success text-white">
            <h6 class="modal-title mb-0"><i class="fa fa-check-circle"></i> Vianda marchada</h6>
        </div>
        <div class="modal-body py-2">
            <div class="text-center mb-2">
                <div class="text-muted small">Código de retiro</div>
                <div style="font-size:1.6rem; font-weight:700;" id="vianda-voucher-codigo">—</div>
            </div>
            <div id="vianda-voucher-aviso" class="alert alert-warning py-1 px-2 small d-none"></div>
            <pre id="vianda-voucher-preview" class="mb-0"></pre>
        </div>
        <div class="modal-footer py-2 d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="vianda-voucher-reimprimir" title="Volver a enviar el voucher a la impresora">
                <i class="fa fa-print"></i> Reimprimir
            </button>
            <button type="button" class="btn btn-sm btn-primary" id="vianda-voucher-cerrar">Listo (nuevo empleado)</button>
        </div>
    </div></div>
</div>

<div id="vianda-procesando-overlay" class="d-none" role="status"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; display: flex; align-items: center; justify-content: center;">
    <div class="bg-white rounded shadow text-center px-4 py-3">
        <i class="fa fa-spinner fa-spin fa-2x text-success mb-2"></i>
        <div><strong>Marchando comanda…</strong></div>
        <div class="small text-muted mt-1">Emitiendo voucher. No cierre ni recargue la página.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
window.VIANDA = {
    tieneCfg: @json($tiene_cfg),
    previewPantalla: @json($preview_pantalla ?? false),
    rutas: {
        apiBase: @json(url('ventas/gastronomia/viandas/proceso/api')),
        inicio: @json(route('inicio')),
    },
    csrf: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/vianda/proceso.js') }}"></script>
@endsection
