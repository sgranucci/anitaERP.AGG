@extends("theme.$theme.layout")
@section('titulo')
    Facturación y cobranzas
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    #tabla-facturacion-cobranzas { font-size: 0.75rem; }
    #factycobr-tabla-repartos-seleccionados thead tr { background:#85C1E9; color:#17202A; }
    .factycobr-seleccion-scroll { max-height: 180px; overflow-y: auto; }
</style>
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_cobranzas/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/facturacion_cobranzas/filtro.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'factycobr-overlay',
    'tituloId' => 'factycobr-titulo',
    'subtituloId' => 'factycobr-subtitulo',
    'titulo' => 'Consultando…',
    'subtitulo' => 'Puede demorar según el período y la lectura Anita. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Facturación y cobranzas</h3>
                <div class="card-tools">
                    <a href="{{ route('facturacion_cobranzas') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_cobranzas') }}" id="form-facturacion-cobranzas" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Informe de facturación y cobranzas (Anita <code>l-factycobr</code>).
                        Une comprobantes del ERP y del bridge Anita por clave tipo|letra|sucursal|número,
                        priorizando el ERP para no duplicar. Los depósitos bancarios se agrupan en <strong>Valores</strong>.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                        $orden = \App\Support\Ventas\FacturacionCobranzasListadoFiltros::normalizarOrden($filtros['orden'] ?? null);
                        $fuente = \App\Support\Ventas\FacturacionCobranzasListadoFiltros::normalizarFuente($filtros['fuente'] ?? null);
                        $incluye = \App\Support\Ventas\FacturacionCobranzasListadoFiltros::normalizarIncluye($filtros['incluye'] ?? null);
                        $repartoModo = \App\Support\Ventas\FacturacionCobranzasListadoFiltros::normalizarRepartoModo($filtros['reparto_modo'] ?? null);
                        $repartosIniciales = $repartos_iniciales ?? [];
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? date('Y-m-d') }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Orden</label>
                        <div class="col-lg-8 pt-1">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="orden" id="orden_comprobante"
                                    value="comprobante"
                                    @checked($orden === \App\Support\Ventas\FacturacionCobranzasListadoFiltros::ORDEN_COMPROBANTE)>
                                <label class="custom-control-label" for="orden_comprobante">Por comprobante</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="orden" id="orden_reparto"
                                    value="reparto"
                                    @checked($orden === \App\Support\Ventas\FacturacionCobranzasListadoFiltros::ORDEN_REPARTO)>
                                <label class="custom-control-label" for="orden_reparto">Por reparto</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Repartos</label>
                        <div class="col-lg-8 pt-1">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="reparto_modo" id="reparto_modo_todos"
                                    value="todos"
                                    @checked($repartoModo === \App\Support\Ventas\FacturacionCobranzasListadoFiltros::REPARTO_MODO_TODOS)>
                                <label class="custom-control-label" for="reparto_modo_todos">Todos</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="reparto_modo" id="reparto_modo_rango"
                                    value="rango"
                                    @checked($repartoModo === \App\Support\Ventas\FacturacionCobranzasListadoFiltros::REPARTO_MODO_RANGO)>
                                <label class="custom-control-label" for="reparto_modo_rango">Rango</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="reparto_modo" id="reparto_modo_particular"
                                    value="particular"
                                    @checked($repartoModo === \App\Support\Ventas\FacturacionCobranzasListadoFiltros::REPARTO_MODO_PARTICULAR)>
                                <label class="custom-control-label" for="reparto_modo_particular">Particulares</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row" id="bloque-rango-reparto"
                        @if ($repartoModo !== \App\Support\Ventas\FacturacionCobranzasListadoFiltros::REPARTO_MODO_RANGO) style="display:none" @endif>
                        <label class="{{ $colLabel }}">Rango repartos</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-5">
                                    <label class="small text-muted mb-1" for="reparto_desde">Desde</label>
                                    <div class="factycobr-reparto-campo" data-campo="desde">
                                        <div class="input-group input-group-sm mb-1">
                                            <input type="text" name="reparto_desde" id="reparto_desde"
                                                class="form-control codigotransporte" autocomplete="off"
                                                title="Código de reparto. Enter valida y avanza. F1 consulta."
                                                value="{{ $filtros['reparto_desde'] ?? '' }}">
                                            <div class="input-group-append">
                                                <button type="button" title="Consulta repartos (F1)"
                                                    class="btn btn-outline-secondary consultatransporte tooltipsC">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="text" name="reparto_desde_nombre" class="form-control form-control-sm nombretransporte" readonly
                                            placeholder="Nombre reparto" value="{{ $filtros['reparto_desde_nombre'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="small text-muted mb-1" for="reparto_hasta">Hasta</label>
                                    <div class="factycobr-reparto-campo" data-campo="hasta">
                                        <div class="input-group input-group-sm mb-1">
                                            <input type="text" name="reparto_hasta" id="reparto_hasta"
                                                class="form-control codigotransporte" autocomplete="off"
                                                title="Código de reparto. Enter valida. F1 consulta."
                                                value="{{ ($filtros['reparto_hasta'] ?? '') === '999999' ? '' : ($filtros['reparto_hasta'] ?? '') }}">
                                            <div class="input-group-append">
                                                <button type="button" title="Consulta repartos (F1)"
                                                    class="btn btn-outline-secondary consultatransporte tooltipsC">
                                                    <i class="fa fa-search"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="text" name="reparto_hasta_nombre" class="form-control form-control-sm nombretransporte" readonly
                                            placeholder="Nombre reparto" value="{{ $filtros['reparto_hasta_nombre'] ?? '' }}">
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Vacío en ambos = todos los repartos del rango.</small>
                        </div>
                    </div>

                    <div class="form-group row" id="bloque-particular-reparto"
                        @if ($repartoModo !== \App\Support\Ventas\FacturacionCobranzasListadoFiltros::REPARTO_MODO_PARTICULAR) style="display:none" @endif>
                        <label class="{{ $colLabel }}">Repartos particulares</label>
                        <div class="col-lg-9">
                            <input type="hidden" name="repartos" id="factycobr_repartos"
                                value="{{ collect($repartosIniciales)->pluck('codigo')->implode(',') }}">
                            <input type="hidden" name="repartos_nombres" id="factycobr_repartos_nombres"
                                value="{{ collect($repartosIniciales)->map(fn ($r) => ($r['codigo'] ?? '').':'.($r['nombre'] ?? ''))->implode('|') }}">

                            <div class="factycobr-reparto-campo factycobr-reparto-puntual mb-2" data-campo="puntual">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="reparto_puntual_codigo"
                                        class="form-control codigotransporte" autocomplete="off"
                                        placeholder="Código" title="Código. Enter agrega. F1 consulta."
                                        style="max-width: 110px;">
                                    <input type="text" class="form-control nombretransporte" id="reparto_puntual_nombre"
                                        readonly placeholder="Nombre reparto">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta repartos (F1)"
                                            class="btn btn-outline-secondary consultatransporte tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-primary" id="factycobr-btn-agregar-reparto"
                                            title="Agregar reparto a la lista">
                                            <i class="fa fa-plus"></i> Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive mb-2 factycobr-seleccion-scroll">
                                <table class="table table-sm table-bordered mb-0" id="factycobr-tabla-repartos-seleccionados">
                                    <thead>
                                        <tr>
                                            <th style="width: 90px;">Código</th>
                                            <th>Nombre</th>
                                            <th style="width: 70px;" class="text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="factycobr-tbody-repartos-seleccionados">
                                        @foreach ($repartosIniciales as $rep)
                                            <tr data-codigo="{{ $rep['codigo'] ?? '' }}">
                                                <td>{{ $rep['codigo'] ?? '' }}</td>
                                                <td>{{ $rep['nombre'] ?? '' }}</td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-outline-danger btn-xs factycobr-btn-quitar-reparto" title="Quitar">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="text-muted small mb-0" id="factycobr-aviso-sin-repartos"
                                @if ($repartosIniciales !== []) style="display: none;" @endif>
                                Agregue uno o más repartos. Enter o F1 en el código; Enter en el modal elige la primera fila.
                            </p>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Fuente</label>
                        <div class="col-lg-8 pt-1">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="fuente" id="fuente_ambos" value="ambos" @checked($fuente === 'ambos')>
                                <label class="custom-control-label" for="fuente_ambos">ERP + Anita</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="fuente" id="fuente_erp" value="erp" @checked($fuente === 'erp')>
                                <label class="custom-control-label" for="fuente_erp">Solo ERP</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="fuente" id="fuente_anita" value="anita" @checked($fuente === 'anita')>
                                <label class="custom-control-label" for="fuente_anita">Solo Anita</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Incluye</label>
                        <div class="col-lg-8 pt-1">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="incluye" id="incluye_todos" value="todos" @checked($incluye === 'todos')>
                                <label class="custom-control-label" for="incluye_todos">Facturas y cobranzas</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="incluye" id="incluye_facturas" value="facturas" @checked($incluye === 'facturas')>
                                <label class="custom-control-label" for="incluye_facturas">Solo facturas</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" class="custom-control-input" name="incluye" id="incluye_cobranzas" value="cobranzas" @checked($incluye === 'cobranzas')>
                                <label class="custom-control-label" for="incluye_cobranzas">Solo cobranzas</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="moneda_id" class="{{ $colLabel }}">Moneda</label>
                        <div class="{{ $colInput }}">
                            <select name="moneda_id" id="moneda_id" class="form-control">
                                @foreach ($moneda_query ?? [] as $mon)
                                    <option value="{{ $mon->id }}" @selected((int) ($filtros['moneda_id'] ?? 1) === (int) $mon->id)>
                                        {{ $mon->nombre ?? $mon->abreviatura ?? $mon->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="moneda_origen" id="moneda_origen" value="1"
                                    @checked(! empty($filtros['moneda_origen']))>
                                <label class="custom-control-label" for="moneda_origen">Solo moneda de origen</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Presentación</label>
                        <div class="col-lg-8 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="solo_totales" id="solo_totales" value="1"
                                    @checked(! empty($filtros['solo_totales']))>
                                <label class="custom-control-label" for="solo_totales">Solo totales (sin detalle)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-factycobr">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Orden:</strong> {{ $orden_texto ?? '' }}
                            · <strong>Repartos:</strong> {{ $reparto_texto ?? '' }}
                            · <strong>Fuente:</strong> {{ $fuente_texto ?? '' }}
                        </p>
                        @foreach ($avisos ?? [] as $aviso)
                            <p class="mb-0 small text-warning mt-1">{{ $aviso }}</p>
                        @endforeach
                        @foreach ($errores ?? [] as $err)
                            <p class="mb-0 small text-danger mt-1">{{ $err }}</p>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_facturacion_cobranzas',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($totales))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Movs:</span>
                                <strong>{{ (int) ($totales['cantidad_detalle'] ?? 0) }}</strong>
                                · Fact. <strong>{{ number_format((float) ($totales['total_comp'] ?? 0), 2, ',', '.') }}</strong>
                                · Cobro <strong>{{ number_format((float) ($totales['total_cobro'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        @endif
                    </div>

                    @php $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasVista ?? [])); @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <div class="px-2 pb-2">
                        <div class="tabla-ancha-grilla" data-tabla-ancha>
                            <div class="tabla-ancha-scroll-top" hidden>
                                <div class="tabla-ancha-scroll-top-inner"></div>
                            </div>
                            <div class="tabla-ancha-wrap">
                                <table id="tabla-facturacion-cobranzas" class="table table-striped table-bordered table-hover table-sm mb-0 tabla-ancha">
                                    @include('ventas.facturacion_cobranzas.partials.tabla_datos', [
                                        'filas' => $filasVista ?? [],
                                        'puede_ver_venta' => $puede_ver_venta ?? false,
                                        'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                        'puede_ver_cobranza' => $puede_ver_cobranza ?? false,
                                    ])
                                </table>
                            </div>
                        </div>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                @if ($filas->total() > 0)
                                    Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} filas
                                @else
                                    Sin registros
                                @endif
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultatransporte')
@endsection
