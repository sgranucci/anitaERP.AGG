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
            cerrarCuenta: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cerrarTodas: @json(route('gastronomia_crown_api_cerrar_todas_cuentas')),
            obsMesa: @json(url('ventas/gastronomia-crown/api/cuenta')),
            articulo: @json(route('gastronomia_crown_api_articulo')),
            descuento: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cliente: @json(url('ventas/gastronomia-crown/api/cuenta')),
            transferir: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cobrar: @json(url('ventas/gastronomia-crown/api/cuenta')),
            cuentasCaja: @json(route('gastronomia_crown_api_cuentas_caja')),
            resolverDescuento: @json(route('gastronomia_crown_api_resolver_descuento')),
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
        empresaId: {{ (int) $config->empresa_id }},
        pideCubiertos: @json((bool) $config->pide_cubiertos),
        soloMarcha: @json((bool) $config->solo_marcha),
        habilitaHotel: @json((bool) $config->habilita_hotel),
        habilitaGst: @json((bool) $config->habilita_canje_gst),
        politicaComanda: @json($config->politica_comanda),
        listaprecioId: {{ (int) ($config->listaprecio_id ?: 0) }},
        listaprecioNombre: @json(optional($config->listaprecio)->nombre),
        skuPrefijo: @json($skuPrefijo),
        skuDigitos: {{ (int) $skuDigitos }},
        kdsRefrescoSegundos: {{ max(5, (int) config('gastronomia.crown.kds_refresco_segundos', 5)) }},
        mercadopagoIcon: @json(asset('assets/pages/img/ventas/gastronomia/mercadopago.svg')),
    };
    // Compat consulta.js compartida (empresa / lista precio del modal de artículos).
    window.GASTRONOMIA = window.GASTRONOMIA || {
        empresaId: {{ (int) $config->empresa_id }},
        listaprecioId: {{ (int) ($config->listaprecio_id ?: 0) }},
        listaprecioNombre: @json(optional($config->listaprecio)->nombre),
    };
</script>
@if ($hayPlanos)
<script src="https://cdn.jsdelivr.net/npm/konva@9/konva.min.js"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/plano_viewer.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/plano_viewer.js')) }}"></script>
@endif
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/usuario/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/descuento_gastronomia/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/descuento_gastronomia/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia_crown/pos.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia_crown/pos.js')) }}"></script>
@endsection

@section('contenido')
<input type="hidden" id="empresa_id" value="{{ (int) $config->empresa_id }}">
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

    @if (($jornada_obligatoria ?? true) && empty(($jornada['jornada_abierta'] ?? false)))
        <div class="alert alert-warning m-2 mb-0 py-2">
            No hay jornada abierta para esta sala.
            Abrila en
            <a href="{{ route('gastronomia_jornada') }}" class="alert-link" target="_blank" rel="noopener">
                Ventas → Gastronomía → Jornada
            </a>
            antes de abrir mesas o cobrar.
        </div>
    @elseif (! empty($jornada['jornada_abierta']) && ! empty($jornada['fecha_jornada_fmt']))
        <div class="alert alert-info m-2 mb-0 py-1 small">
            Jornada abierta: <strong>{{ $jornada['fecha_jornada_fmt'] }}</strong>
            @if (! empty($jornada['sala_nombre']))
                · {{ $jornada['sala_nombre'] }}
            @endif
        </div>
    @endif

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
            <button type="button" class="gc-fkey-btn gc-fkey-ok" data-fkey="F5" id="gc-btn-f5" title="Factura 100% efectivo (como Anita)">
                <i class="fa fa-money-bill-wave"></i>
                <span class="gc-fkey-label"><kbd>F5</kbd> Efectivo</span>
            </button>
            <button type="button" class="gc-fkey-btn" data-fkey="F6" id="gc-btn-f6" title="Borrar ítem marcado">
                <i class="fa fa-eraser"></i>
                <span class="gc-fkey-label"><kbd>F6</kbd> Borrar</span>
            </button>
            <button type="button" class="gc-fkey-btn" data-fkey="F7" id="gc-btn-f7" title="Grilla de medios de cobro">
                <i class="fa fa-credit-card"></i>
                <span class="gc-fkey-label"><kbd>F7</kbd> Medios</span>
            </button>
            <button type="button" class="gc-fkey-btn" data-fkey="F8" id="gc-btn-f8" title="Descuento con autorizante (carga_dto_autorizante)">
                <i class="fa fa-user-check"></i>
                <span class="gc-fkey-label"><kbd>F8</kbd> Dto.</span>
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
        <div class="col-xl-2 col-lg-2 col-md-3 mb-2 gc-col-mesas">
            <div class="card card-outline card-info gc-panel h-100">
                <div class="card-header py-1 px-2" id="gc-panel-cuentas-wrap">
                    <div class="d-flex justify-content-between align-items-center flex-wrap w-100">
                        <h3 class="card-title mb-0">
                            Cuentas abiertas
                            <span class="badge badge-info ml-1" id="gc-cuentas-contador">0</span>
                        </h3>
                        <div class="card-tools ml-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1" id="gc-btn-nueva-cuenta"
                                    title="Abrir cuenta de barra / sin mesa (cliente eventual)">
                                <i class="fa fa-plus"></i> Barra
                            </button>
                            <button type="button" class="btn btn-tool" id="gc-btn-refresh-mesas" title="Actualizar">
                                <i class="fa fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="gc-cuentas-ayuda mt-1 mb-0 w-100">Sin facturar · clic para abrir</div>
                    <div id="gc-panel-cuentas" class="gc-panel-cuentas-lista mt-1 mb-1 w-100"></div>
                    <div class="gc-cuentas-acciones w-100">
                        <button type="button" class="btn btn-sm btn-outline-danger d-none" id="gc-btn-cerrar-cuenta"
                                title="Cerrar la cuenta activa sin facturar">
                            <i class="fa fa-times"></i> Cerrar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="gc-btn-cerrar-todas"
                                title="Cerrar todas las cuentas abiertas sin facturar">
                            <i class="fa fa-trash"></i> Cerrar todas
                        </button>
                    </div>
                </div>
                <div class="card-header py-1 px-2 border-top">
                    @php
                        $tituloPanelMesas = $usaMesas ? ($hayPlanos ? 'Planos / mesas' : 'Mesas') : 'Listado';
                        $tituloPanelMesasHint = $usaMesas ? 'Mesa libre abre cuenta' : 'Cuentas de la terminal';
                    @endphp
                    <h3 class="card-title mb-0" title="{{ $tituloPanelMesasHint }}">
                        {{ $tituloPanelMesas }}
                    </h3>
                </div>
                <div class="card-body p-1 gc-panel-scroll" id="gc-panel-mesas">
                    <div class="text-muted small p-2">Cargando…</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-4 col-md-9 mb-2">
            <div class="card card-outline card-primary gc-panel h-100">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Carga</h3>
                </div>
                <div class="card-body gc-carga-body">
                    <div class="tm-articulo-campo" id="gc-campo-articulo" data-articulo-solo-facturable="1">
                        <div class="gc-carga-codigo-row">
                            <label class="gc-carga-hint" for="gc-codigo-articulo">C&oacute;digo · Enter · +cant · F1</label>
                            <input type="hidden" class="articulo_id" value="">
                            <input type="hidden" class="codigoarticulo" value="">
                            <input type="hidden" id="gc-cantidad" value="1">
                            <div class="input-group input-group-sm gc-carga-codigo-group">
                                @if ($skuDigitos > 0)
                                    <div class="input-group-prepend">
                                        <span class="input-group-text gc-sku-prefijo">{{ $skuPrefijo }}</span>
                                    </div>
                                    <input type="text" id="gc-codigo-articulo" class="form-control gc-codigo-articulo"
                                           autocomplete="off" inputmode="numeric"
                                           maxlength="{{ (int) $skuDigitos }}"
                                           placeholder="00001">
                                @else
                                    <input type="text" id="gc-codigo-articulo" class="form-control gc-codigo-articulo"
                                           autocomplete="off" placeholder="SKU">
                                @endif
                                <div class="input-group-append">
                                    <button type="button"
                                            class="btn btn-outline-secondary consultaarticulo"
                                            data-solo-facturable="1"
                                            data-requiere-precio="1"
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
                        <div id="gc-desc-articulo" class="gc-carga-desc" aria-live="polite"></div>
                        <input type="hidden" class="descripcionarticulo" id="gc-desc-articulo-hidden" value="">
                    </div>
                    <div class="gc-carga-meta">
                        <div class="gc-carga-kds">
                            <label class="gc-carga-hint" for="gc-area-kds">KDS</label>
                            <select id="gc-area-kds" class="form-control form-control-sm" title="Destino cocina (vac&iacute;o = seg&uacute;n categor&iacute;a)">
                                <option value="">Auto</option>
                            </select>
                        </div>
                        <div class="gc-carga-prepara">
                            <label class="gc-carga-hint d-block" for="gc-prepara">Prepara</label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="gc-prepara" checked>
                                <label class="custom-control-label" for="gc-prepara" id="gc-prepara-label">Sí</label>
                            </div>
                        </div>
                        <div class="gc-carga-precio text-right">
                            <span class="gc-carga-hint d-block">Precio</span>
                            <strong id="gc-precio-articulo" class="gc-precio-preview">—</strong>
                        </div>
                    </div>
                    <div class="gc-carga-aviso" id="gc-estado-cuenta">
                        @if ($usaMesas)
                            Tocá una mesa libre para abrir su cuenta, o + para una cuenta sin mesa.
                        @else
                            Tocá + para abrir una cuenta, o elegí una de la lista.
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6 col-lg-6 mb-2" id="gc-col-detalle">
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
                                    <th style="width:4.5rem;">Cant</th>
                                    <th style="width:5.5rem;">Cód.</th>
                                    <th>Artículo</th>
                                    <th class="text-right" style="width:5.5rem;">Imp.</th>
                                    <th style="width:5.5rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" class="text-muted text-center py-3">Sin ítems</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Destino KDS por ítem (reemplaza prompt feo) --}}
<div class="modal fade" id="gc-modal-destino-kds" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-info text-white">
                <h6 class="modal-title mb-0">
                    <i class="fa fa-utensils"></i> Destino KDS
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="gc-destino-articulo"></p>
                <label class="small font-weight-bold d-block mb-1">Área de cocina / barra</label>
                <div class="gc-kds-chips" id="gc-destino-areas" role="group" aria-label="Áreas KDS"></div>
                <input type="hidden" id="gc-destino-area-id" value="0">
                <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top">
                    <div>
                        <label class="small font-weight-bold d-block mb-0" for="gc-destino-prepara">¿Prepara en cocina?</label>
                        <small class="text-muted">Si está apagado, no sale en el KDS</small>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="gc-destino-prepara" checked>
                        <label class="custom-control-label" for="gc-destino-prepara" id="gc-destino-prepara-label">Sí</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="gc-destino-guardar">
                    <i class="fa fa-check"></i> Aplicar
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Observación del ítem para la comanda (paridad AGG comentario cocina) --}}
<div class="modal fade" id="gc-modal-obs-item" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2 bg-primary text-white">
                <h6 class="modal-title mb-0">
                    <i class="fa fa-sticky-note"></i> Observación del ítem
                </h6>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="gc-obs-articulo"></p>
                <label class="small font-weight-bold mb-1" for="gc-obs-texto">Indicaciones para la comanda / KDS</label>
                <textarea class="form-control" id="gc-obs-texto" rows="3" maxlength="255"
                          placeholder="Ej. sin cebolla, bien cocido, aparte…" autofocus></textarea>
                <small class="form-text text-muted">Se imprime / muestra en la comanda de cocina.</small>
                <input type="hidden" id="gc-obs-linea-id" value="">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-danger mr-auto" id="gc-obs-limpiar" title="Quitar observación">
                    Quitar
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="gc-obs-guardar">
                    <i class="fa fa-check"></i> Guardar
                </button>
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
@include('includes.caja.modalconsultacuentacaja')
@include('includes.admin.modalconsultausuario')
@include('includes.ventas.modalconsultacliente')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.stock.modalconsultadescuento')

{{-- Cobranza multi-medio (solo Crown). F5 efectivo va directo sin este modal. --}}
<div class="modal fade" id="gc-modal-cobrar" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content gc-cobro-modal">
            <div class="modal-header gc-cobro-header py-3">
                <div>
                    <h5 class="modal-title mb-0">Medios de cobro</h5>
                    <div class="gc-cobro-sub" id="gc-cobro-cuenta">Cuenta</div>
                </div>
                <div class="gc-cobro-total-wrap text-right">
                    <div class="gc-cobro-total-label">Total a cobrar</div>
                    <div class="gc-cobro-total" id="gc-cobro-total">$ 0,00</div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="gc-medios-rapidos" id="gc-medios-rapidos" aria-label="Medios rápidos"></div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered mb-0 bg-white" id="gc-cuenta-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width:46%;">Cuenta de caja</th>
                                <th style="width:10%;">Mon.</th>
                                <th style="width:22%;">Monto</th>
                                <th style="width:8%;"></th>
                            </tr>
                        </thead>
                        <tbody id="gc-tbody-cuenta-table"></tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap align-items-center mt-2" style="gap:0.45rem;">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="gc-agrega-renglon-cuenta">
                        <i class="fa fa-plus"></i> Agregar renglón
                    </button>
                    <div class="ml-auto gc-cobro-resumen" id="gc-totales-cobranza"></div>
                </div>
                <div class="gc-cobro-atajos mt-3">
                    @if ($usaMesas)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cobro="factura_sola">Factura sola</button>
                    @endif
                    @if ($config->habilita_hotel)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cobro="hotel">Hotel</button>
                    @endif
                    <span class="small text-muted ml-auto">
                        Opcional: <kbd>F7</kbd> abre esta grilla · <kbd>F5</kbd> cobra todo en efectivo sin modal
                    </span>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-success" id="gc-cobrar-medios">
                    <i class="fa fa-check"></i> Facturar con estos medios
                </button>
            </div>
        </div>
    </div>
</div>

<template id="gc-template-renglon-cuenta">
    <tr class="item-cuenta-gc">
        <td>
            <div class="gc-cc-cuenta-wrap tm-cuentacaja-campo">
                <input type="hidden" class="cuentacaja_id" value="">
                <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacaja">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigo" value="" placeholder="Cód." autocomplete="off">
                <input type="text" class="form-control form-control-sm nombre" value="" placeholder="Descripción" readonly>
            </div>
        </td>
        <td class="moneda-label align-middle">—</td>
        <td>
            <input type="hidden" class="moneda_id" value="">
            <input type="number" step="0.01" class="form-control form-control-sm monto" value="">
        </td>
        <td class="text-center align-middle">
            <button type="button" title="Eliminar" class="btn-accion-tabla gc-eliminar-cuenta">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>

{{-- F8 carga_dto_autorizante (solo Crown) --}}
<div class="modal fade" id="gc-modal-f8" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content gc-f8-modal">
            <div class="modal-header gc-f8-header py-3">
                <div>
                    <h5 class="modal-title mb-0">Descuento con autorizante</h5>
                    <div class="gc-cobro-sub">Invitados · Staff · Descuento (como Anita)</div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="gc-f8-tipos" role="group" aria-label="Tipo de descuento">
                    <button type="button" class="gc-f8-tipo active" data-tipo="invitados">
                        <i class="fa fa-glass-cheers"></i><span>Invitados</span>
                    </button>
                    <button type="button" class="gc-f8-tipo" data-tipo="staff">
                        <i class="fa fa-user-tie"></i><span>Staff</span>
                    </button>
                    <button type="button" class="gc-f8-tipo" data-tipo="descuento">
                        <i class="fa fa-percent"></i><span>Descuento</span>
                    </button>
                </div>
                <input type="hidden" id="gc-f8-tipo" value="invitados">

                <div class="form-row mt-3">
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-1">Autoriza <span class="text-danger">*</span></label>
                        <div class="gc-campo-consulta tm-usuario-campo" id="gc-f8-autorizante">
                            <input type="hidden" class="usuario_id" id="gc-f8-autorizante-id" value="">
                            <button type="button" class="btn-accion-tabla consultausuario" title="Consulta usuario (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm usuario_codigo_arbol" id="gc-f8-autorizante-codigo" placeholder="Usuario" autocomplete="off">
                            <input type="text" class="form-control form-control-sm nombreusuario" id="gc-f8-autorizante-nombre" placeholder="Nombre" readonly>
                        </div>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-1">Centro de costo <span class="text-danger">*</span></label>
                        <div class="gc-campo-consulta tm-centrocosto-campo" id="gc-f8-ccosto">
                            <input type="hidden" class="centrocosto_id" id="gc-f8-ccosto-id" value="">
                            <button type="button" class="btn-accion-tabla consultacentrocosto" title="Consulta CC (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm codigocentrocosto" id="gc-f8-ccosto-codigo" placeholder="Código" autocomplete="off">
                            <input type="text" class="form-control form-control-sm descripcioncentrocosto" id="gc-f8-ccosto-nombre" placeholder="Descripción" readonly>
                        </div>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-1">Cliente interno <span class="text-danger">*</span></label>
                        <div class="gc-campo-consulta tm-cliente-campo" id="gc-f8-cliente">
                            <input type="hidden" class="cliente_id" id="gc-f8-cliente-id" value="">
                            <button type="button" class="btn-accion-tabla consultacliente" title="Consulta cliente (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm codigocliente" id="gc-f8-cliente-codigo" placeholder="Código" autocomplete="off">
                            <input type="text" class="form-control form-control-sm nombrecliente" id="gc-f8-cliente-nombre" placeholder="Nombre" readonly>
                        </div>
                    </div>
                    <div class="form-group col-md-6 mb-2" id="gc-f8-bloque-codigo">
                        <label class="small mb-1">Código descuento</label>
                        <div class="gc-campo-consulta" id="gc-f8-descuento">
                            <input type="hidden" class="descuento_gastronomia_id" id="gc-f8-descuento-id" value="">
                            <button type="button" class="btn-accion-tabla consultadescuento" title="Consulta descuento (F1)">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm codigodescuento" id="gc-f8-descuento-codigo" placeholder="Código" autocomplete="off">
                            <input type="text" class="form-control form-control-sm nombredescuento" id="gc-f8-descuento-nombre" placeholder="Descripción" readonly>
                        </div>
                        <small class="text-muted" id="gc-f8-dto-valor"></small>
                    </div>
                </div>
                <p class="small text-muted mb-0">
                    Invitados/Staff aplican 100%. Tipo Descuento usa el maestro. Luego factura (sin cobranza si queda cortesía).
                </p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-sm btn-primary" id="gc-f8-confirmar">
                    <i class="fa fa-file-invoice"></i> Facturar
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
