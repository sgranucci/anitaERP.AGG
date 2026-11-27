@extends("theme.$theme.layout")
@section('titulo')
    Tablero de compras
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/tablero_oc/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/tablero_oc/filtro.js')) ?: time() }}"></script>
@if ($graficos !== null)
<script>
    window.__tableroCompras = @json($graficos);
</script>
<script src="{{ asset('assets/pages/scripts/compras/tablero_oc/tablero.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/tablero_oc/tablero.js')) ?: time() }}"></script>
@endif
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tablero-compras-overlay',
    'tituloId' => 'tablero-compras-overlay-titulo',
    'subtituloId' => 'tablero-compras-overlay-subtitulo',
    'titulo' => 'Armando el tablero…',
    'subtitulo' => 'Puede demorar según el período y las empresas elegidas. No cierre la página.',
])
@include('includes.tabs-activas-estilos')
@php
    $claseTabNav = [];
    $claseTabPane = [];
    foreach (['compras', 'detalle', 'compradores', 'proveedores', 'precios', 'ahorros'] as $claveTab) {
        $claseTabNav[$claveTab] = $filtros['tab'] === $claveTab ? 'active' : '';
        $claseTabPane[$claveTab] = $filtros['tab'] === $claveTab ? 'show active' : '';
    }
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    #form-tablero-compras .tc-campo-corto { max-width: 10rem; }
    .tc-kpi { border: 1px solid #85C1E9; border-radius: .25rem; background: #fff; padding: .6rem .8rem; height: 100%; }
    .tc-kpi .tc-kpi-valor { font-size: 1.6rem; font-weight: 600; color: #1B4F72; line-height: 1.1; }
    .tc-kpi .tc-kpi-etiqueta { font-size: .78rem; color: #566573; text-transform: uppercase; letter-spacing: .02em; }
    .tc-kpi .tc-kpi-nota { font-size: .7rem; color: #7f8c8d; }
    .tc-grafico { position: relative; height: 280px; }
    .tc-grafico.tc-alto { height: 420px; }
    .tc-card-titulo { font-size: .95rem; font-weight: 600; }
    .tc-tabla thead tr { background-color: #85C1E9; color: #17202A; }
    .tc-tabla thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: .75rem; }
    .tc-tabla tbody td { font-size: .75rem; vertical-align: middle; }
    #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
    #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: .65rem; }
    #tabla-paginada tbody td { font-size: .65rem; vertical-align: middle; }
</style>

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Tablero de compras</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('consultar_tablero_compras') }}" class="btn btn-outline-secondary btn-sm" title="Volver a los valores por defecto">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('consultar_tablero_compras') }}" id="form-tablero-compras" class="mb-0">
                <div class="card-body pb-2">
                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'reporte_clave' => 'tablero_compras_oc',
                        'mostrar_consolidar' => false,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="anio" class="{{ $colLabel }}">A&ntilde;o</label>
                        <div class="{{ $colInput }}">
                            <select name="anio" id="anio" class="form-control tc-campo-corto">
                                @foreach ($anios as $anioOpcion)
                                    <option value="{{ $anioOpcion }}" @selected((int) $filtros['anio'] === (int) $anioOpcion)>{{ $anioOpcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="mes" class="{{ $colLabel }}">Mes / d&iacute;a</label>
                        <div class="{{ $colInput }} d-flex">
                            <select name="mes" id="mes" class="form-control mr-2" style="max-width: 11rem;">
                                <option value="0" @selected((int) $filtros['mes'] === 0)>Todo el a&ntilde;o</option>
                                @foreach ($meses_nombres as $numeroMes => $nombreMes)
                                    <option value="{{ $numeroMes }}" @selected((int) $filtros['mes'] === (int) $numeroMes)>{{ $nombreMes }}</option>
                                @endforeach
                            </select>
                            <select name="dia" id="dia" class="form-control" style="max-width: 7rem;" data-valor="{{ (int) $filtros['dia'] }}">
                                <option value="0">Todos</option>
                                @for ($d = 1; $d <= 31; $d++)
                                    <option value="{{ $d }}" @selected((int) $filtros['dia'] === $d)>{{ $d }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="usuarios" class="{{ $colLabel }}">Comprador</label>
                        <div class="{{ $colInput }}">
                            <div id="tablero-usuario-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="usuarios" id="usuarios" class="form-control codigousuario"
                                        placeholder="Vac&iacute;o = todos; 2,5,8" autocomplete="off"
                                        title="F1 o lupa: consulta usuarios"
                                        value="{{ $filtros['usuarios'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta usuarios (F1)"
                                            class="btn btn-outline-secondary consultausuario-tablero tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metausuario" readonly
                                    value="{{ $meta_usuarios ?? 'Todos los compradores' }}">
                            </div>
                        </div>
                        <label for="proveedores" class="{{ $colLabel }}">Proveedor</label>
                        <div class="{{ $colInput }}">
                            <div id="tablero-proveedor-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="proveedores" id="proveedores" class="form-control tablero-codigo-proveedor"
                                        placeholder="Vac&iacute;o = todos; c&oacute;digos separados por coma" autocomplete="off"
                                        title="F1 o lupa: consulta proveedores"
                                        value="{{ $filtros['proveedores'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta proveedores (F1)"
                                            class="btn btn-outline-secondary consultaproveedor-tablero tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metaproveedor" readonly
                                    value="{{ $meta_proveedores ?? 'Todos los proveedores' }}">
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado" class="{{ $colLabel }}">Estado de la OC</label>
                        <div class="{{ $colInput }}">
                            <select name="estado" id="estado" class="form-control">
                                @foreach ($opciones_estado as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected($filtros['estado'] === $opcion['valor'])>{{ $opcion['etiqueta'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="vencimiento" class="{{ $colLabel }}">Vencimiento</label>
                        <div class="{{ $colInput }}">
                            <select name="vencimiento" id="vencimiento" class="form-control">
                                @foreach ($opciones_vencimiento as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected($filtros['vencimiento'] === $opcion['valor'])>{{ $opcion['etiqueta'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="com" class="{{ $colLabel }}">COM asociada</label>
                        <div class="{{ $colInput }}">
                            <select name="com" id="com" class="form-control">
                                @foreach ($opciones_com as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected($filtros['com'] === $opcion['valor'])>{{ $opcion['etiqueta'] }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">COM = recepci&oacute;n de mercader&iacute;a confirmada (no es la factura).</small>
                        </div>
                        <label for="factura" class="{{ $colLabel }}">Factura de proveedor</label>
                        <div class="{{ $colInput }}">
                            <select name="factura" id="factura" class="form-control">
                                @foreach ($opciones_factura as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected($filtros['factura'] === $opcion['valor'])>{{ $opcion['etiqueta'] }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Comprobante de proveedor cargado contra la OC.</small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <input type="hidden" name="tab" id="tablero-tab" value="{{ $filtros['tab'] }}">
                            <input type="hidden" name="articulo_id" id="tablero-articulo-id" value="{{ (int) $filtros['articulo_id'] }}">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($resultado !== null)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Per&iacute;odo:</strong> {{ $periodo_texto }}
                            @if (! empty($subtitulo))
                                &middot; {{ $subtitulo }}
                            @endif
                        </p>
                    </div>

                    <div class="px-3 pt-3 tabs-activas">
                        <ul class="nav nav-tabs" id="tablero-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['compras'] }}" data-toggle="tab" data-tab="compras" href="#tab-tablero-compras" role="tab">
                                    <i class="fa fa-shopping-cart"></i> Compras
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['detalle'] }}" data-toggle="tab" data-tab="detalle" href="#tab-tablero-detalle" role="tab">
                                    <i class="fa fa-list"></i> Detalle de OC
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['compradores'] }}" data-toggle="tab" data-tab="compradores" href="#tab-tablero-compradores" role="tab">
                                    <i class="fa fa-user"></i> Compradores
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['proveedores'] }}" data-toggle="tab" data-tab="proveedores" href="#tab-tablero-proveedores" role="tab">
                                    <i class="fa fa-truck"></i> Proveedores
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['precios'] }}" data-toggle="tab" data-tab="precios" href="#tab-tablero-precios" role="tab">
                                    <i class="fa fa-line-chart"></i> Precios
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $claseTabNav['ahorros'] }}" data-toggle="tab" data-tab="ahorros" href="#tab-tablero-ahorros" role="tab">
                                    <i class="fa fa-piggy-bank"></i> Ahorros
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content p-3">
                        <div class="tab-pane fade {{ $claseTabPane['compras'] }}" id="tab-tablero-compras" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_compras')
                        </div>
                        <div class="tab-pane fade {{ $claseTabPane['detalle'] }}" id="tab-tablero-detalle" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_detalle')
                        </div>
                        <div class="tab-pane fade {{ $claseTabPane['compradores'] }}" id="tab-tablero-compradores" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_compradores')
                        </div>
                        <div class="tab-pane fade {{ $claseTabPane['proveedores'] }}" id="tab-tablero-proveedores" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_proveedores')
                        </div>
                        <div class="tab-pane fade {{ $claseTabPane['precios'] }}" id="tab-tablero-precios" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_precios')
                        </div>
                        <div class="tab-pane fade {{ $claseTabPane['ahorros'] }}" id="tab-tablero-ahorros" role="tabpanel">
                            @include('compras.tablero_oc.partials.tab_ahorros')
                        </div>
                    </div>

                    @include('compras.tablero_oc.partials.definiciones')
                </div>
            @else
                <div class="card-body border-top">
                    <p class="text-muted mb-0">Elija al menos una empresa y pulse <strong>Consultar</strong>.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@include('includes.compras.modalconsultaproveedor')
@endsection
