@extends("theme.$theme.layout")

@section('titulo')
    Facturador canjes marketing
@endsection

@section('styles')
<style>
    #cm-pos-shell.cm-bloqueado { filter: blur(2px); pointer-events: none; user-select: none; }
    #modal-cm-login-mozo .modal-dialog {
        max-width: 420px;
        margin: 1.75rem auto;
    }
    #modal-cm-login-mozo.show { display: flex !important; align-items: center; min-height: calc(100% - 3.5rem); }
    #cm-login-mozo-form .cm-login-clave-decoy {
        position: absolute;
        left: -9999px;
        width: 1px;
        height: 1px;
        overflow: hidden;
        opacity: 0;
        pointer-events: none;
    }
    #cm-login-clave-mozo.cm-login-clave-mask {
        -webkit-text-security: disc;
        text-security: disc;
    }
    .cm-cuenta-activa-bar {
        border-left: 4px solid #6f42c1;
        background: linear-gradient(90deg, #e2d9f3 0%, #faf8ff 100%);
        padding: 0.4rem 0.75rem;
        margin-bottom: 0.5rem;
    }
    #cm-panel-cuentas .btn-gastro-cuenta-activa {
        box-shadow: 0 0 0 3px rgba(111, 66, 193, 0.45);
        font-weight: 700;
    }
    .cm-columnas-principales { align-items: stretch; }
    .cm-columnas-principales > [class*="col-"] { display: flex; flex-direction: column; }
    .cm-card-consumo-carga, .cm-card-detalle-cuenta { flex: 1 1 auto; }
    .cm-panel-lineas { max-height: 42vh; overflow-y: auto; }
    .cm-sku-input { font-size: 1.1rem; font-weight: 600; letter-spacing: 0.04em; }
    .cm-campo-articulo-carga {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 0.35rem;
    }
    .cm-campo-articulo-carga .btn-accion-tabla {
        flex: 0 0 auto;
    }
    .cm-cantidad-linea .btn-cm-qty {
        min-width: 1.75rem;
        padding-left: 0.35rem;
        padding-right: 0.35rem;
    }
    .cm-panel-descuento-vip .cm-fila-campo {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 0.45rem;
        min-width: 0;
    }
    .cm-panel-descuento-vip .cm-fila-campo:last-child { margin-bottom: 0; }
    .cm-panel-descuento-vip .cm-fila-label {
        flex: 0 0 78px;
        font-size: 0.82rem;
        color: #6c757d;
        margin: 0;
        white-space: nowrap;
        line-height: 1.2;
    }
    .cm-panel-descuento-vip .cm-fila-label.requerido::after { content: ' *'; color: #dc3545; }
    .cm-panel-descuento-vip .cm-campo-consulta {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .cm-panel-descuento-vip .cm-campo-codigo-desc {
        width: 64px;
        flex: 0 0 64px;
    }
    .cm-panel-descuento-vip .cm-campo-nombre-desc {
        flex: 1 1 280px;
        min-width: 240px;
        max-width: none;
    }
    .cm-panel-descuento-vip .cm-campo-vip-cod {
        width: 80px;
        flex: 0 0 80px;
    }
    .cm-panel-descuento-vip .cm-campo-vip-doc {
        width: 120px;
        flex: 0 0 120px;
    }
    .cm-panel-descuento-vip .cm-campo-vip-nombre {
        flex: 1 1 260px;
        min-width: 220px;
        max-width: none;
    }
    .cm-panel-descuento-vip .btn-accion-tabla,
    .cm-panel-descuento-vip .btn { flex: 0 0 auto; }
    .cm-panel-descuento-vip .cm-fila-wigos {
        padding-left: 86px;
        margin-top: 0.35rem;
    }
    #modal-cm-f8-descuento .cm-campo-consulta {
        display: flex; flex-wrap: nowrap; align-items: center; gap: 6px; min-width: 0;
    }
    #toast-container > .toast-warning {
        background-color: #856404 !important;
        color: #fff !important;
    }
    #toast-container > .toast-warning .toast-title,
    #toast-container > .toast-warning .toast-message {
        color: #fff !important;
    }
    .cm-campo-id { width: 72px; flex: 0 0 72px; }
    .cm-campo-codigo { width: 100px; flex: 0 0 100px; }
    .cm-campo-nombre { flex: 1 1 auto; min-width: 0; }
    .gastro-campo-consulta {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        min-width: 0;
    }
    .gastro-campo-consulta .gastro-campo-id { width: 72px; flex: 0 0 72px; }
    .gastro-campo-consulta .gastro-campo-codigo { width: 88px; flex: 0 0 88px; }
    .gastro-campo-consulta .gastro-campo-nombre { flex: 1 1 auto; min-width: 0; }
    .gastro-campo-consulta .btn-accion-tabla { flex: 0 0 auto; }

    #modal-opcionales .modal-body {
        padding: 0;
        background: #f7f9fc;
    }
    #modal-opcionales .gastro-opc-progreso {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: #fff;
        border-bottom: 1px solid #e3e6ea;
    }
    #modal-opcionales .gastro-opc-progreso-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    #modal-opcionales .gastro-opc-progreso-titulo {
        font-size: 0.78rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    #modal-opcionales .gastro-opc-progreso-subtitulo {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1f2937;
        line-height: 1.2;
        margin-top: 0.1rem;
    }
    #modal-opcionales .gastro-opc-progreso-pasos {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    #modal-opcionales .gastro-opc-paso {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.2rem 0.55rem 0.2rem 0.35rem;
        background: #eef1f5;
        color: #6c757d;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid transparent;
        cursor: pointer;
        user-select: none;
        transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
    }
    #modal-opcionales .gastro-opc-paso .gastro-opc-paso-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.3rem;
        height: 1.3rem;
        border-radius: 50%;
        background: #d6dbe2;
        color: #495057;
        font-size: 0.72rem;
        font-weight: 700;
    }
    #modal-opcionales .gastro-opc-paso.completado {
        background: #e6f4ea;
        color: #1e7e34;
        border-color: #c3e6cb;
    }
    #modal-opcionales .gastro-opc-paso.completado .gastro-opc-paso-num {
        background: #28a745;
        color: #fff;
    }
    #modal-opcionales .gastro-opc-paso.actual {
        background: #007bff;
        color: #fff;
        border-color: #0069d9;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.18);
    }
    #modal-opcionales .gastro-opc-paso.actual .gastro-opc-paso-num {
        background: #fff;
        color: #0056b3;
    }
    #modal-opcionales .gastro-opc-paso.faltante {
        animation: gastroOpcShake 0.45s linear;
        background: #fdecea;
        color: #b02a37;
        border-color: #f5c6cb;
    }
    #modal-opcionales .gastro-opc-paso.faltante .gastro-opc-paso-num {
        background: #dc3545;
        color: #fff;
    }
    @keyframes gastroOpcShake {
        0%,100% { transform: translateX(0); }
        25% { transform: translateX(-3px); }
        75% { transform: translateX(3px); }
    }
    #modal-opcionales .gastro-opc-pasos-wrap {
        position: relative;
        padding: 1rem 1rem 0.5rem;
    }
    #modal-opcionales .gastro-opc-grupo {
        display: none;
        animation: gastroOpcFadeIn 0.18s ease-out;
    }
    #modal-opcionales .gastro-opc-grupo.activo {
        display: block;
    }
    @keyframes gastroOpcFadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    #modal-opcionales .gastro-opc-grupo-titulo {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.6rem;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    #modal-opcionales .gastro-opc-grupo-titulo .gastro-opc-pill {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 0.15rem 0.5rem;
        background: #007bff;
        color: #fff;
        border-radius: 999px;
    }
    #modal-opcionales .gastro-opc-grupo-titulo small {
        font-size: 0.78rem;
        font-weight: 500;
        color: #6c757d;
        text-transform: none;
        letter-spacing: 0;
    }
    #modal-opcionales .gastro-opc-grilla {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
        gap: 0.6rem;
    }
    #modal-opcionales .gastro-opc-tarjeta {
        position: relative;
        border: 2px solid #dee2e6;
        border-radius: 0.5rem;
        background: #fff;
        padding: 0.7rem 0.75rem 0.7rem 2.1rem;
        cursor: pointer;
        transition: border-color 0.12s ease, box-shadow 0.12s ease, background 0.12s ease, transform 0.08s ease;
        display: flex;
        flex-direction: column;
        min-height: 76px;
        user-select: none;
    }
    #modal-opcionales .gastro-opc-tarjeta:focus {
        outline: none;
        border-color: #80bdff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }
    #modal-opcionales .gastro-opc-atajo {
        position: absolute;
        top: 6px;
        left: 6px;
        min-width: 1.4rem;
        height: 1.4rem;
        line-height: 1.4rem;
        text-align: center;
        font-size: 0.74rem;
        font-weight: 700;
        color: #495057;
        background: #f1f3f5;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 0 0.3rem;
    }
    #modal-opcionales .gastro-opc-tarjeta.seleccionada .gastro-opc-atajo {
        color: #fff;
        background: #007bff;
        border-color: #0069d9;
    }
    #modal-opcionales .gastro-opc-tarjeta:hover {
        border-color: #80bdff;
        background: #f6fbff;
        transform: translateY(-1px);
    }
    #modal-opcionales .gastro-opc-tarjeta.seleccionada {
        border-color: #007bff;
        background: #e7f1ff;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }
    #modal-opcionales .gastro-opc-tarjeta.seleccionada::after {
        content: '\f00c';
        font-family: FontAwesome, 'Font Awesome 5 Free', sans-serif;
        font-weight: 900;
        position: absolute;
        top: 6px;
        right: 8px;
        color: #007bff;
        font-size: 0.85rem;
    }
    #modal-opcionales .gastro-opc-sku {
        font-size: 0.78rem;
        font-weight: 700;
        color: #6c757d;
        margin-bottom: 0.15rem;
    }
    #modal-opcionales .gastro-opc-tarjeta.seleccionada .gastro-opc-sku {
        color: #0056b3;
    }
    #modal-opcionales .gastro-opc-descripcion {
        font-size: 0.95rem;
        color: #212529;
        line-height: 1.25;
        word-break: break-word;
    }
    #modal-opcionales .gastro-opc-resumen {
        margin: 0.75rem 1rem 0;
        background: #fff;
        border: 1px solid #e3e6ea;
        border-radius: 0.4rem;
        padding: 0.5rem 0.75rem;
    }
    #modal-opcionales .gastro-opc-resumen-titulo {
        font-size: 0.7rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.35rem;
    }
    #modal-opcionales .gastro-opc-resumen-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    #modal-opcionales .gastro-opc-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.2rem 0.6rem;
        background: #f1f3f5;
        color: #495057;
        border-radius: 999px;
        font-size: 0.78rem;
        line-height: 1.2;
    }
    #modal-opcionales .gastro-opc-chip.completado {
        background: #e6f4ea;
        color: #1e7e34;
    }
    #modal-opcionales .gastro-opc-chip .gastro-opc-chip-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.05rem;
        height: 1.05rem;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.08);
        color: inherit;
        font-size: 0.65rem;
        font-weight: 700;
    }
    #modal-opcionales .gastro-opc-chip.completado .gastro-opc-chip-num {
        background: #28a745;
        color: #fff;
    }
    #modal-opcionales .gastro-opc-leyenda {
        padding: 0.4rem 1rem 0.6rem;
        font-size: 0.75rem;
        color: #6c757d;
        text-align: center;
    }
    #modal-opcionales .gastro-opc-leyenda kbd {
        font-size: 0.7rem;
        padding: 0.05rem 0.3rem;
    }
    #modal-opcionales .modal-footer {
        background: #fff;
    }
    #modal-opcionales .gastro-opc-grupo.gastro-opc-faltante .gastro-opc-grilla {
        outline: 2px dashed #dc3545;
        outline-offset: 6px;
        border-radius: 0.5rem;
        animation: gastroOpcShake 0.45s linear;
    }
</style>
@endsection

@section('contenido')
<div class="d-flex justify-content-between align-items-center flex-wrap mb-2" id="cm-toolbar-salir">
    <span class="text-muted small">
        Facturador canjes marketing
        @if ($tiene_cfg_pv && $empresa_nombre)
            — Terminal: <strong>{{ $identificador_pc_actual }}</strong>
            · Empresa: <strong>{{ $empresa_nombre }}</strong>
        @endif
    </span>
    <a href="{{ route('inicio') }}" class="btn btn-sm btn-outline-secondary" id="cm-btn-salir-pantalla" title="Volver al menú principal">
        <i class="fa fa-sign-out"></i> Salir
    </a>
</div>

<div id="cm-pos-shell" class="cm-bloqueado">
    @if (! $tiene_cfg_pv)
        <div class="alert alert-danger">
            Sin configuración de punto de venta gastronomía para este equipo
            (<code>{{ $identificador_pc_actual }}</code>).
            Configure en <a href="{{ route('consultar_configuracion_puntoventa_gastronomia') }}">Configuración PV gastronomía</a>.
        </div>
    @else
        <div class="cm-cuenta-activa-bar d-flex justify-content-between align-items-center flex-wrap" id="cm-bar-cuenta">
            <div>
                <span class="badge badge-secondary" id="cm-mozo-badge">Sin mozo</span>
                <strong id="cm-cuenta-label" class="ml-2">Cuenta —</strong>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cm-btn-cambiar-mozo" title="Cambiar mozo / nueva cuenta">
                    <i class="fa fa-user"></i> Cambiar mozo
                </button>
            </div>
        </div>

        <div class="card card-outline card-info mb-3" id="cm-card-cuentas-activas">
            <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-0"><i class="fa fa-folder-open"></i> Cuentas abiertas en esta terminal</h3>
                <span class="text-muted small">Seleccione una cuenta o abra otra con el mozo</span>
            </div>
            <div class="card-body py-2">
                <div id="cm-panel-cuentas" class="row"></div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-success" id="cm-btn-nueva-cuenta" title="Nueva cuenta (mozo)"><i class="fa fa-plus"></i> Nueva cuenta</button>
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" id="cm-btn-cerrar-cuenta" title="Cerrar cuenta sin facturar"><i class="fa fa-times"></i> Cerrar cuenta</button>
                    <button type="button" class="btn btn-sm btn-outline-warning" id="cm-btn-cerrar-todas-cuentas" title="Cerrar todas las cuentas abiertas de esta terminal sin facturar"><i class="fa fa-trash"></i> Cerrar todas</button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8 mb-3">
                <div class="card card-outline card-warning mb-0 cm-panel-descuento-vip" id="cm-panel-descuento-vip">
                    <div class="card-header py-2">
                        <h3 class="card-title mb-0"><i class="fa fa-percent"></i> Descuento y cliente VIP</h3>
                    </div>
                    <div class="card-body py-2" id="cm-descuento-vip-slot-original">
                        <p id="cm-descuento-vip-aviso-f8" class="small text-muted mb-0 d-none">
                            Al facturar (<kbd>F8</kbd>) cargue el cliente VIP en el modal.
                        </p>
                        <div id="cm-descuento-vip-movable">
                        <input type="hidden" id="descuento_id" value="">
                        <div class="cm-fila-campo">
                            <label class="cm-fila-label" for="cm-codigodescuento">Descuento</label>
                            <div class="cm-campo-consulta">
                                <input type="text" class="form-control form-control-sm cm-campo-codigo-desc" id="cm-codigodescuento" value="{{ $descuento_codigo }}" readonly title="Código descuento">
                                <input type="text" class="form-control form-control-sm cm-campo-nombre-desc" id="cm-nombredescuento" readonly placeholder="Nombre" title="Nombre descuento">
                            </div>
                        </div>
                        <div class="cm-fila-campo">
                            <label class="cm-fila-label requerido" for="cliente_vip_numeroid">Cliente VIP</label>
                            <div class="cm-campo-consulta">
                                <input type="hidden" id="cliente_vip_id" value="">
                                <input type="text" class="form-control form-control-sm cm-campo-vip-cod" id="cliente_vip_numeroid" placeholder="Cód." autocomplete="off" title="Código VIP">
                                <input type="text" class="form-control form-control-sm cm-campo-vip-doc" id="cliente_vip_documento" placeholder="DNI" autocomplete="off" title="Documento">
                                <input type="text" class="form-control form-control-sm cm-campo-vip-nombre" id="cliente_vip_nombre" placeholder="Nombre" readonly title="Apellido y nombre">
                                <button type="button" class="btn-accion-tabla consultaclientevip" title="Consultar clientes VIP"><i class="fa fa-search"></i></button>
                            </div>
                        </div>
                        <div id="cm-vip-aviso" class="alert alert-warning py-1 px-2 small d-none mb-0 mt-1" role="alert"></div>
                        @if ($wigos_account_info_habilitado)
                        <div class="cm-fila-wigos">
                            <button type="button" class="btn btn-sm btn-outline-info" id="cm-btn-abrir-wigos" title="Leer tarjeta Wigos"><i class="fa fa-credit-card"></i> Tarjeta Wigos</button>
                        </div>
                        @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row cm-columnas-principales">
            <div class="col-xl-5 mb-3">
                <div class="card card-outline card-success mb-3 cm-card-consumo-carga">
                    <div class="card-header py-2"><span><i class="fa fa-barcode"></i> Carga de artículo</span></div>
                    <div class="card-body py-2">
                        <p class="small text-muted mb-2">
                            @if ($sku_catalogo_digitos_sufijo > 0)
                                Ingrese los dígitos del SKU; <kbd>Enter</kbd> agrega cantidad <strong>1</strong>.
                            @else
                                Ingrese el SKU; <kbd>Enter</kbd> agrega cantidad <strong>1</strong>.
                            @endif
                            <kbd>F1</kbd> o la lupa abren la consulta de artículos.
                            <kbd>+</kbd> o botón <strong>Agregar</strong> abren el modal de cantidad (opcionales primero si aplican).
                            <kbd>Tab</kbd> en SKU resuelve el artículo y enfoca Agregar.
                            Factura siempre a <strong>Consumidor final</strong>. Descuento código <strong>{{ $descuento_codigo }}</strong>.
                            <kbd>F8</kbd> facturar con descuento.
                        </p>
                        <div class="cm-campo-articulo-carga mb-1" id="cm-campo-articulo-carga">
                            <input type="hidden" class="articulo_id" id="cm-articulo_id" value="">
                            <input type="hidden" class="categoria_id" value="">
                            <input type="hidden" class="subcategoria_id" value="">
                            <input type="hidden" class="unidadmedida" value="">
                            <button type="button"
                                    title="Consulta artículos — F1 (catálogo SKU {{ $sku_catalogo_prefijo }})"
                                    class="btn-accion-tabla consultaarticulo tooltipsC"
                                    data-sku-prefijo-filtro="{{ $sku_catalogo_prefijo }}"
                                    data-sku-digitos-filtro="{{ (int) $sku_catalogo_digitos_sufijo }}"
                                    data-listaprecio-id="{{ (int) ($listaprecio_id ?? config('precio.listaprecio_default_id', 1)) }}"
                                    data-listaprecio-nombre="{{ $listaprecio_nombre ?? '' }}">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <div class="col-auto px-0">
                                <label class="mb-0 small text-muted d-block" for="cm-sku-input">SKU</label>
                                @if ($sku_catalogo_digitos_sufijo > 0)
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">{{ $sku_catalogo_prefijo }}</span></div>
                                        <input type="text" id="cm-sku-input" class="form-control cm-sku-input gastro-sku-sufijo gastro-carga-sku codigoarticulo" maxlength="20" autocomplete="off" placeholder="" inputmode="numeric" pattern="[0-9]*">
                                    </div>
                                @else
                                    <input type="text" id="cm-sku-input" class="form-control form-control-sm cm-sku-input codigoarticulo gastro-carga-sku" maxlength="30" autocomplete="off" placeholder="SKU">
                                @endif
                            </div>
                            <div class="col-auto px-0 flex-grow-1" style="min-width:140px;">
                                <label class="mb-0 small text-muted d-block" for="cm-articulo-descripcion">Descripción</label>
                                <input type="text" id="cm-articulo-descripcion" class="form-control form-control-sm descripcionarticulo" placeholder="Descripción" readonly autocomplete="off">
                            </div>
                            <div class="col-auto px-0 pb-1">
                                <label class="mb-0 small text-muted d-block invisible" aria-hidden="true">Acción</label>
                                <button type="button" class="btn btn-sm btn-outline-success" id="cm-btn-agregar-cantidad" title="Agregar con cantidad (+)"><i class="fa fa-plus"></i></button>
                                <button type="button" class="btn btn-sm btn-success" id="cm-btn-agregar-sku" title="Agregar (modal cantidad)"><i class="fa fa-plus"></i> Agregar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7 mb-3">
                <div class="card card-outline card-secondary mb-3 cm-card-detalle-cuenta">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                        <span><i class="fa fa-list"></i> Ítems a facturar</span>
                        <button type="button" class="btn btn-sm btn-warning" id="cm-btn-f8" title="F8 — Facturar con descuento"><i class="fa fa-percent"></i> Facturar desc.</button>
                    </div>
                    <div class="card-body p-2 cm-panel-lineas">
                        <table class="table table-sm table-striped mb-0" id="cm-tabla-lineas">
                            <thead><tr><th>SKU</th><th>Descripción</th><th class="text-right">Cant.</th><th class="text-right">Importe</th><th></th></tr></thead>
                            <tbody id="cm-tbody-lineas"><tr><td colspan="5" class="text-muted text-center">Sin consumos</td></tr></tbody>
                        </table>
                    </div>
                    <div class="card-footer py-2 text-right">
                        <strong>Total estimado: $<span id="cm-total-estimado">0,00</span></strong>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Login mozo — consulta estándar gastronomía (código + lupa + clave) --}}
<div class="modal fade" id="modal-cm-login-mozo" tabindex="-1" role="dialog" aria-labelledby="modal-cm-login-mozo-titulo">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-primary">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title mb-0" id="modal-cm-login-mozo-titulo"><i class="fa fa-user-circle mr-1"></i> Ingreso mozo — canjes marketing</h5>
                <button type="button" class="close text-white" id="cm-login-cerrar" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                @if ($tiene_cfg_pv && $empresa_nombre)
                    <div class="alert alert-info py-2 mb-3 small">
                        Terminal: <strong>{{ $identificador_pc_actual }}</strong>
                        · Empresa: <strong>{{ $empresa_nombre }}</strong>
                        — los mozos se filtran por esta terminal y empresa del punto de venta.
                    </div>
                @endif
                <p class="small text-muted mb-3">Identifíquese para abrir una cuenta. Busque con la lupa, o ingrese <strong>ID</strong> o <strong>código</strong> y pulse <kbd>Enter</kbd> para validar y pasar a la clave.</p>
                <form id="cm-login-mozo-form" autocomplete="off" onsubmit="return false;">
                    <div class="cm-login-clave-decoy" aria-hidden="true">
                        <input type="text" tabindex="-1" autocomplete="username">
                        <input type="password" tabindex="-1" autocomplete="current-password">
                    </div>
                    <div class="form-group mb-2">
                        <label class="mb-1">Mozo</label>
                        <div id="modal-cm-login-mozo-mozo-wrap" class="gastro-campo-consulta">
                            <input type="text" class="form-control form-control-sm gastro-campo-id mozo_gastronomia_id" id="cm-login-mozo_gastronomia_id" value="" placeholder="ID" autocomplete="off">
                            <button type="button" title="Consulta mozos" class="btn-accion-tabla consultamozo tooltipsC">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm gastro-campo-codigo codigomozo" id="cm-login-codigomozo" value="" placeholder="Código" autocomplete="off" autofocus>
                            <input type="text" class="form-control form-control-sm gastro-campo-nombre nombremozo" id="cm-login-nombremozo" value="" placeholder="Nombre" readonly>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label for="cm-login-clave-mozo">Clave</label>
                        <input type="text" class="form-control cm-login-clave-mask" id="cm-login-clave-mozo" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" inputmode="text">
                    </div>
                </form>
                <div class="alert alert-danger d-none mt-2 mb-0 py-2" id="cm-login-error"></div>
            </div>
            <div class="modal-footer py-2 d-flex flex-wrap justify-content-between">
                <a href="{{ route('inicio') }}" class="btn btn-outline-secondary" id="cm-login-salir">
                    <i class="fa fa-sign-out"></i> Salir al menú
                </a>
                <button type="button" class="btn btn-primary" id="cm-login-confirmar">Ingresar y abrir cuenta</button>
            </div>
        </div>
    </div>
</div>

{{-- F8: descuento + VIP (panel movible desde JS) --}}
<div class="modal fade" id="modal-cm-f8-descuento" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0">Facturar canje marketing</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Revise el descuento prefijado e indique el <strong>cliente VIP</strong> beneficiario.
                    La factura se emite a <strong>Consumidor final</strong>.
                </p>
                <div id="cm-f8-slot-descuento-vip"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-cm-f8-confirmar">Facturar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-cm-cantidad" tabindex="-1" role="dialog" aria-labelledby="modal-cm-cantidad-titulo">
<div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
    <div class="modal-header py-2"><h6 class="modal-title mb-0" id="modal-cm-cantidad-titulo">Cantidad</h6><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button></div>
    <div class="modal-body"><input type="number" min="0.0001" step="any" class="form-control" id="cm-modal-cantidad-valor" value="1" autocomplete="off"></div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary" id="cm-modal-cantidad-ok">Agregar <small class="text-white-50">(Enter)</small></button>
    </div>
</div></div></div>

<div class="modal fade" id="modal-opcionales" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">
                    Opcionales del artículo
                    <small class="text-muted ml-2" id="modal-opcionales-articulo-info"></small>
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="modal-opcionales-body"></div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <div class="d-flex" style="gap: 0.4rem;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="modal-opcionales-atras" disabled>
                        <i class="fa fa-arrow-left"></i> Atrás
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="modal-opcionales-confirmar">
                        Siguiente <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($wigos_account_info_habilitado)
<div class="modal fade" id="modal-cm-wigos-vip" tabindex="-1" role="dialog" aria-labelledby="modal-cm-wigos-vip-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="modal-cm-wigos-vip-title">Tarjeta Wigos — cliente VIP</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="small text-muted mb-2">
                    Pase la tarjeta por el lector: se valida y muestra el titular aquí.
                    Pulse <kbd>Enter</kbd> o <strong>Aplicar al cliente VIP</strong> para cargar el beneficiario del descuento.
                    Si el titular no existe como VIP, se crea al confirmar.
                </p>
                <div class="form-group mb-2">
                    <label for="cm-wigos-trackdata" class="small mb-1">Tarjeta / trackdata</label>
                    <input type="text" class="form-control form-control-sm" id="cm-wigos-trackdata" autocomplete="off">
                </div>
                <div id="cm-wigos-error" class="alert alert-danger py-2 small d-none" role="alert"></div>
                <div id="cm-wigos-preview" class="d-none border rounded p-2 bg-light small">
                    <div><strong>Titular:</strong> <span id="cm-wigos-prev-nombre">—</span></div>
                    <div><strong>Documento:</strong> <span id="cm-wigos-prev-documento">—</span></div>
                    <div><strong>Cód. VIP:</strong> <span id="cm-wigos-prev-codigo">—</span></div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="cm-wigos-aplicar" disabled>Aplicar al cliente VIP</button>
            </div>
        </div>
    </div>
</div>
@endif

@include('includes.ventas.modalconsultaclientevip')
@include('includes.stock.modalconsultamozo')
@include('includes.stock.modalconsultaarticulo')

<!-- Modal comentario cocina (KDS Waitry) -->
<div class="modal fade" id="modal-comentario-cocina" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Comentario para cocina</h6>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body py-2">
                <p class="small text-muted mb-2" id="modal-comentario-cocina-articulo"></p>
                <label class="small mb-1" for="fld-comentario-cocina">Indicaciones para la comanda (KDS)</label>
                <textarea class="form-control form-control-sm" id="fld-comentario-cocina" rows="3" maxlength="255" placeholder="Ej. sin cebolla, bien cocido…"></textarea>
                <small class="form-text text-muted">Se envía a Waitry en el ítem al facturar.</small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="modal-comentario-cocina-guardar">Guardar</button>
            </div>
        </div>
    </div>
</div>

<div id="cm-facturacion-procesando-overlay"
     class="d-none"
     role="status"
     aria-live="assertive"
     aria-hidden="true"
     style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
    <div class="bg-white rounded shadow text-center px-4 py-3" style="max-width: 92vw; min-width: 18rem;">
        <i class="fa fa-spinner fa-spin fa-2x text-warning mb-2" aria-hidden="true"></i>
        <div><strong id="cm-facturacion-procesando-titulo">Procesando…</strong></div>
        <div class="small text-muted mt-1" id="cm-facturacion-procesando-subtitulo">Por favor espere. No cierre ni recargue la página.</div>
    </div>
</div>
@endsection

@section('scripts')
<script>
window.GASTRONOMIA = {
    empresaId: {{ (int) ($empresa_id ?? 0) }},
    listaprecioId: {{ (int) ($listaprecio_id ?? config('precio.listaprecio_default_id', 1)) }},
    listaprecioNombre: @json($listaprecio_nombre ?? ''),
};
window.CANJE_MARKETING = {
    rutasSalir: {
        inicio: @json(route('inicio')),
    },
    rutas: {
        apiBase: @json(url('ventas/gastronomia/canjes/api')),
    },
    descuentoCodigo: @json($descuento_codigo),
    monedaFacturaId: @json($moneda_factura_id ?? 1),
    skuCatalogoPrefijo: @json($sku_catalogo_prefijo),
    skuCatalogoDigitosSufijo: @json($sku_catalogo_digitos_sufijo),
    wigosHabilitado: @json($wigos_account_info_habilitado),
    imprimirTicket: @json((bool) config('gastronomia.ticket_impresion_automatica', true)),
    tieneCfgPv: @json($tiene_cfg_pv),
    csrfToken: @json(csrf_token()),
};
</script>
<script src="{{ asset('assets/pages/scripts/ventas/mozo_gastronomia/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/canjes/cliente_vip/consulta_pos.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/canjes/proceso_facturacion.js') }}"></script>
@endsection
