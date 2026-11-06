@extends("theme.$theme.layout")
@section('titulo')
    POS Crown
@endsection

@php
    $planos = $planos ?? [];
    $hayPlanos = count($planos) > 0;
    $skuPrefijo = \App\Support\Ventas\GastronomiaSkuCatalogoSupport::prefijo();
    $skuDigitos = \App\Support\Ventas\GastronomiaSkuCatalogoSupport::digitosSufijo();
@endphp

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/gastronomia-crown-pos.css') }}?v={{ @filemtime(public_path('assets/css/gastronomia-crown-pos.css')) }}">
@if ($hayPlanos)
<link rel="stylesheet" href="{{ asset('assets/css/gastronomia-crown-plano-editor.css') }}?v={{ @filemtime(public_path('assets/css/gastronomia-crown-plano-editor.css')) }}">
@endif
@endsection

@section('scripts')
<script>
    window.GastroCrownPos = {
        urls: {
            config: @json(route('gastronomia_crown_api_config')),
            mesas: @json(route('gastronomia_crown_api_mesas')),
            cuentas: @json(route('gastronomia_crown_api_cuentas')),
            abrirMesa: @json(route('gastronomia_crown_api_abrir_mesa')),
            abrirCuenta: @json(route('gastronomia_crown_api_abrir_cuenta')),
            cuentaVer: @json(url('ventas/gastronomia-crown/api/cuenta')),
            agregarLinea: @json(url('ventas/gastronomia-crown/api/cuenta')),
            marcha: @json(url('ventas/gastronomia-crown/api/cuenta')),
            separador: @json(url('ventas/gastronomia-crown/api/cuenta')),
            anular: @json(url('ventas/gastronomia-crown/api/cuenta')),
            obsMesa: @json(url('ventas/gastronomia-crown/api/cuenta')),
            articulo: @json(route('gastronomia_crown_api_articulo')),
            descuento: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cliente: @json(url('ventas/gastronomia-crown/api/cuenta')),
            transferir: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cobrar: @json(url('ventas/gastronomia-crown/api/cuenta')),
            areasKds: @json(route('gastronomia_crown_api_areas_kds')),
            listaPrecio: @json(url('ventas/gastronomia-crown/api/cuenta')),
            opcionalesArticulo: @json(url('ventas/gastronomia-crown/api/opcionales-articulo')),
            articuloBase: @json(url('ventas/gastronomia-crown/api/articulo')),
            kds: @json(route('gastronomia_crown_kds')),
            planosEstado: @json(route('gastronomia_crown_api_planos_estado')),
        },
        modo: @json($modo),
        usaMesas: @json($usaMesas),
        planos: @json($planos),
        hotkeys: @json($hotkeys),
        herramientas: @json($herramientas),
        configId: {{ (int) $config->id }},
        pideCubiertos: @json((bool) $config->pide_cubiertos),
        soloMarcha: @json((bool) $config->solo_marcha),
        habilitaHotel: @json((bool) $config->habilita_hotel),
        habilitaGst: @json((bool) $config->habilita_canje_gst),
        politicaComanda: @json($config->politica_comanda),
        listaprecioId: {{ (int) ($config->listaprecio_id ?: 0) }},
        skuPrefijo: @json($skuPrefijo),
        skuDigitos: {{ (int) $skuDigitos }},
    };
</script>
@if ($hayPlanos)
<script src="https://cdn.jsdelivr.net/npm/konva@9/konva.min.js"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/plano_viewer.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/plano_viewer.js')) }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/pos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/pos.js')) }}"></script>
@endsection

@section('contenido')
<div class="gc-pos" id="gc-pos-root" tabindex="0">
    <div class="gc-pos-topbar">
        <div class="gc-pos-brand">
            <span class="gc-pos-brand-mark">Crown</span>
            <span class="gc-pos-brand-sub">POS gastronomía</span>
        </div>
        <div class="gc-pos-meta">
            <span class="badge badge-info gc-pos-modo">{{ $modoLabel }}</span>
            <span class="gc-pos-pc text-muted">{{ $config->identificador_pc }}</span>
            @if ($config->descripcion)
                <span class="text-muted">· {{ $config->descripcion }}</span>
            @endif
            @if ($config->solo_marcha)
                <span class="badge badge-warning">Solo marcha</span>
            @endif
            @if ($config->habilita_hotel)
                <span class="badge badge-secondary">Hotel</span>
            @endif
            @if ($config->habilita_canje_gst)
                <span class="badge badge-success">GST/Wigos</span>
            @endif
        </div>
        <div class="gc-pos-actions-top">
            <a href="{{ route('gastronomia_crown_kds', ['sala_id' => $config->sala_id]) }}"
               class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-tv"></i> KDS
            </a>
            <a href="{{ route('editar_configuracion_puntoventa_gastronomia', $config->id) }}"
               class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                <i class="fa fa-cog"></i> Config PC
            </a>
        </div>
    </div>

    {{-- Teclas F: botones con icono (además de hotkeys) --}}
    <div class="gc-pos-fkey-bar" id="gc-pos-fkey-bar">
        <button type="button" class="gc-fkey-btn" data-fkey="F1" id="gc-btn-f1" title="Consultar artículo">
            <i class="fa fa-search"></i>
            <span class="gc-fkey-label"><kbd>F1</kbd> Consulta</span>
        </button>
        @if ($config->habilita_hotel)
            <button type="button" class="gc-fkey-btn" data-fkey="F2" id="gc-btn-f2" title="Cargo a habitación">
                <i class="fa fa-hotel"></i>
                <span class="gc-fkey-label"><kbd>F2</kbd> Hotel</span>
            </button>
        @endif
        @unless ($config->solo_marcha)
            <button type="button" class="gc-fkey-btn gc-fkey-ok" data-fkey="F5" id="gc-btn-f5" title="Factura efectivo">
                <i class="fa fa-money-bill-wave"></i>
                <span class="gc-fkey-label"><kbd>F5</kbd> Efectivo</span>
            </button>
            <button type="button" class="gc-fkey-btn" data-fkey="F6" id="gc-btn-f6" title="Borrar ítem marcado">
                <i class="fa fa-eraser"></i>
                <span class="gc-fkey-label"><kbd>F6</kbd> Borrar</span>
            </button>
            <button type="button" class="gc-fkey-btn" data-fkey="F8" id="gc-btn-f8" title="Interna / GST">
                <i class="fa fa-ticket-alt"></i>
                <span class="gc-fkey-label"><kbd>F8</kbd> Interna</span>
            </button>
            @if ($usaMesas)
                <button type="button" class="gc-fkey-btn" data-fkey="F9" id="gc-btn-f9" title="Factura sola">
                    <i class="fa fa-file-invoice"></i>
                    <span class="gc-fkey-label"><kbd>F9</kbd> Fact. sola</span>
                </button>
            @endif
            <button type="button" class="gc-fkey-btn" data-fkey="F10" id="gc-btn-f10" title="Descuento mesa">
                <i class="fa fa-percent"></i>
                <span class="gc-fkey-label"><kbd>F10</kbd> Dto.</span>
            </button>
        @else
            <button type="button" class="gc-fkey-btn" data-fkey="F6" id="gc-btn-f6" title="Borrar ítem marcado">
                <i class="fa fa-eraser"></i>
                <span class="gc-fkey-label"><kbd>F6</kbd> Borrar</span>
            </button>
        @endunless
        <button type="button" class="gc-fkey-btn" data-fkey="F11" id="gc-btn-f11" title="Observación ítem">
            <i class="fa fa-sticky-note"></i>
            <span class="gc-fkey-label"><kbd>F11</kbd> Obs. ítem</span>
        </button>
        <button type="button" class="gc-fkey-btn gc-fkey-fire" data-fkey="F12" id="gc-btn-f12"
                title="Marcha a cocina"
                {{ $config->politica_comanda === 'sin_comanda' ? 'disabled' : '' }}>
            <i class="fa fa-fire"></i>
            <span class="gc-fkey-label"><kbd>F12</kbd> Marcha</span>
        </button>
    </div>

    {{-- Herramientas Anita (KEY_HERRAMIENTA) como botones con icono --}}
    <div class="gc-pos-tools" id="gc-pos-tools">
        <span class="gc-tools-title"><i class="fa fa-toolbox"></i> Herramientas</span>
        @foreach ($herramientas as $h)
            <button type="button"
                    class="gc-tool-btn{{ empty($h['enabled']) ? ' disabled' : '' }}"
                    data-action="{{ $h['action'] }}"
                    data-code="{{ $h['code'] }}"
                    title="{{ $h['label'] }} ({{ $h['code'] }})"
                    {{ empty($h['enabled']) ? 'disabled' : '' }}>
                <i class="fa {{ $h['icon'] }}"></i>
                <span class="gc-tool-label">{{ $h['label'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="row gc-pos-columns">
        <div class="col-lg-3 col-md-4 mb-2">
            <div class="card card-outline card-info gc-panel h-100">
                <div class="card-header py-2">
                    @php
                        $tituloPanelMesas = $usaMesas ? ($hayPlanos ? 'Planos' : 'Mesas') : 'Cuentas';
                    @endphp
                    <h3 class="card-title mb-0">
                        {{ $tituloPanelMesas }}
                        @if ($usaMesas)
                            <small class="d-block text-muted font-weight-normal">Mesa libre abre su cuenta. El + abre una cuenta sin mesa.</small>
                        @endif
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool text-primary" id="gc-btn-nueva-cuenta" title="Nueva cuenta">
                            <i class="fa fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-tool" id="gc-btn-refresh-mesas" title="Actualizar">
                            <i class="fa fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2 gc-panel-scroll" id="gc-panel-mesas">
                    <div class="text-muted small p-2">Cargando…</div>
                </div>
                @if ($usaMesas)
                    <div class="card-footer p-2" id="gc-panel-cuentas"></div>
                @endif
            </div>
        </div>
        <div class="col-lg-4 col-md-8 mb-2">
            <div class="card card-outline card-primary gc-panel h-100">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Carga</h3>
                </div>
                <div class="card-body">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-2 tm-articulo-campo" id="gc-campo-articulo" data-articulo-solo-facturable="1">
                            <label class="small mb-1" for="gc-codigo-articulo">Código · Enter suma 1 · + cantidad · F1</label>
                            <input type="hidden" class="articulo_id" value="">
                            <input type="hidden" class="codigoarticulo" value="">
                            <input type="hidden" id="gc-cantidad" value="1">
                            <div class="input-group">
                                @if ($skuDigitos > 0)
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">{{ $skuPrefijo }}</span>
                                    </div>
                                @endif
                                @if ($skuDigitos > 0)
                                    <input type="text" id="gc-codigo-articulo" class="form-control form-control-lg"
                                           autocomplete="off" inputmode="numeric"
                                           maxlength="{{ (int) $skuDigitos }}"
                                           placeholder="{{ (int) $skuDigitos }} dígitos">
                                @else
                                    <input type="text" id="gc-codigo-articulo" class="form-control form-control-lg"
                                           autocomplete="off" placeholder="SKU / código">
                                @endif
                                <div class="input-group-append">
                                    <button type="button"
                                            class="btn btn-outline-secondary consultaarticulo"
                                            data-solo-facturable="1"
                                            data-sku-prefijo-filtro="{{ $skuPrefijo }}"
                                            data-sku-digitos-filtro="{{ (int) $skuDigitos }}"
                                            data-listaprecio-id="{{ (int) ($config->listaprecio_id ?: 0) }}"
                                            data-listaprecio-nombre="{{ optional($config->listaprecio)->nombre }}"
                                            title="Consulta art&iacute;culos (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-6 mb-2">
                            <label class="small mb-1" for="gc-area-kds">KDS destino</label>
                            <select id="gc-area-kds" class="form-control form-control-lg">
                                <option value="">Auto (subcategoría)</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <label class="small mb-1 d-block">Prepara</label>
                            <div class="custom-control custom-switch mt-2">
                                <input type="checkbox" class="custom-control-input" id="gc-prepara" checked>
                                <label class="custom-control-label" for="gc-prepara" id="gc-prepara-label">Sí</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-1" for="gc-desc-articulo">Descripción del ítem</label>
                        <input type="text" id="gc-desc-articulo" class="form-control form-control-lg descripcionarticulo" readonly>
                    </div>
                    <div class="alert alert-secondary py-2 small mb-0" id="gc-estado-cuenta">
                        @if ($usaMesas)
                            Tocá una mesa libre para abrir su cuenta, o + para una cuenta sin mesa.
                        @else
                            Tocá + para abrir una cuenta, o elegí una de la lista.
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-2" id="gc-col-detalle">
            <div class="card card-outline card-info gc-panel h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0" id="gc-detalle-titulo">Detalle</h3>
                    <div class="text-right">
                        <div class="small text-muted" id="gc-subtotal-cuenta" style="display:none;"></div>
                        <strong id="gc-total-cuenta">$ 0,00</strong>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive gc-panel-scroll">
                        <table class="table table-sm table-hover mb-0" id="gc-tabla-lineas">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:2rem;"></th>
                                    <th>Cant</th>
                                    <th>Artículo</th>
                                    <th class="text-right">Imp.</th>
                                    <th style="width:2.2rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="5" class="text-muted text-center py-3">Sin ítems</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Opcionales de receta (paridad AGG: paso por orden, 1-9 / Enter / ← / Esc) --}}
<div class="modal fade" id="gc-modal-opcionales" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">
                    Opcionales del artículo
                    <small class="text-muted ml-2" id="gc-opc-info"></small>
                </h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="gc-opc-progreso">
                <span class="gc-opc-progreso-titulo">Personalizá el pedido</span>
                <div class="gc-opc-pasos" id="gc-opc-pasos"></div>
            </div>
            <div class="modal-body" id="gc-opc-body"></div>
            <div class="gc-opc-leyenda">
                <kbd>1</kbd>–<kbd>9</kbd> elegir · <kbd>Enter</kbd> siguiente / aceptar ·
                <kbd>&larr;</kbd> volver · <kbd>Esc</kbd> cancelar
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <div class="d-flex" style="gap: 0.4rem;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gc-opc-atras" disabled>
                        <i class="fa fa-arrow-left"></i> Atrás
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="gc-opc-confirmar">
                        Siguiente <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')

<div class="modal fade" id="gc-modal-cobrar" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title">Cobrar</h6>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="mb-1" id="gc-cobro-cuenta">Cuenta</div>
                <div class="h4 mb-3" id="gc-cobro-total">$ 0,00</div>
                <p class="small text-muted mb-2">Se emite la factura electrónica. Elegí el medio.</p>
                <div class="d-flex flex-wrap" style="gap: 0.4rem;">
                    <button type="button" class="btn btn-sm btn-success" data-cobro="efectivo">Efectivo (F5)</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-cobro="interna">Interna / GST</button>
                    @if ($usaMesas)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cobro="factura_sola">Factura sola</button>
                    @endif
                    @if ($config->habilita_hotel)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cobro="hotel">Hotel</button>
                    @endif
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
@endsection
