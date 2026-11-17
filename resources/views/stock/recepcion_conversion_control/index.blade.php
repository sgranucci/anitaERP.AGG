@extends("theme.$theme.layout")
@section('titulo')
    Control conversión recepción
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    #rcc-overlay.d-none { display: none !important; }
    .rcc-tabla td:nth-child(7),
    .rcc-tabla td:nth-child(14) { white-space: normal; min-width: 10rem; }
    .rcc-header-empresa td { background: #1B4F72; color: #fff; font-weight: 600; }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/reportes/empresas_checkboxes.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_conversion_control/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_conversion_control/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'rcc-overlay',
    'tituloId' => 'rcc-overlay-titulo',
    'subtituloId' => 'rcc-overlay-subtitulo',
    'titulo' => 'Consultando conversiones…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-exchange"></i> Control conversión recepción</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_control_conversion_recepcion') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_control_conversion_recepcion') }}" class="mb-0" id="form-recepcion-conversion-control">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Controla las conversiones de artículos de compra (UM de remito → UM de stock)
                        y, si tienen insumo asociado por SKU alternativo, la cantidad y el costo que llegan al insumo
                        (TRA a depósito fórmula o cálculo teórico desde el maestro).
                        Filtro principal: empresas (todas a la vez) y rango de fechas. Solo recepciones confirmadas.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'recepcion_conversion_control',
                        'mostrar_consolidar' => true,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde"
                                class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta"
                                class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedor" class="{{ $colLabel }}">Proveedor</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="proveedor" id="proveedor" class="form-control"
                                placeholder="Código o nombre"
                                value="{{ $filtros['proveedor'] ?? '' }}">
                        </div>
                        <label for="sku" class="{{ $colLabel }}">Artículo</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="sku" id="sku" class="form-control"
                                placeholder="SKU o descripción"
                                value="{{ $filtros['sku'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Alcance</label>
                        <div class="{{ $colInput }} pt-1">
                            <div class="custom-control custom-checkbox">
                                <input type="hidden" name="solo_con_insumo" value="0">
                                <input type="checkbox" class="custom-control-input" name="solo_con_insumo" id="solo_con_insumo" value="1"
                                    @checked(! empty($filtros['solo_con_insumo']))>
                                <label class="custom-control-label" for="solo_con_insumo">
                                    Solo artículos de compra con insumo asociado
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">{{ $subtitulo ?? '' }}</p>
                        @php $tot = $resultado['totales'] ?? []; @endphp
                        <p class="mb-0 small text-muted">
                            Líneas: {{ (int) ($tot['lineas'] ?? 0) }}
                            · Con insumo: {{ (int) ($tot['con_insumo'] ?? 0) }}
                            · Convertidas TRA: {{ (int) ($tot['convertidas_tra'] ?? 0) }}
                            · Teóricas: {{ (int) ($tot['teoricas'] ?? 0) }}
                        </p>
                        <p class="mb-0 small text-muted">
                            Filas en amarillo: conversión teórica (el COM no pasó por depósito fórmula; el cálculo sale del maestro).
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_control_conversion_recepcion',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filas instanceof \Illuminate\Pagination\LengthAwarePaginator
                                ? $filas->getCollection()
                                : ($filas ?? []))
                                ->map(fn ($f) => (object) ['nombreempresa' => is_array($f) ? ($f['nombreempresa'] ?? '') : ''])
                        );
                    @endphp
                    @if (! empty($logosVista))
                        <div class="px-3 pt-2 d-flex align-items-center flex-wrap">
                            @foreach ($logosVista as $logo)
                                @if (! empty($logo['url']))
                                    <img src="{{ $logo['url'] }}" alt="" style="max-height:42px;margin-right:8px;">
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="px-3 pb-1">
                        <p class="small text-muted mb-1">
                            Tabla ancha: usá la <strong>barra de scroll de arriba</strong>, o Shift + rueda del mouse / deslizá el trackpad.
                        </p>
                    </div>
                    <div class="tabla-ancha-grilla px-3 pb-2" data-tabla-ancha style="--tabla-ancha-c1: 5.5rem;">
                        <div class="tabla-ancha-scroll-top" hidden>
                            <div class="tabla-ancha-scroll-top-inner"></div>
                        </div>
                        <div class="tabla-ancha-wrap">
                            @include('stock.recepcion_conversion_control.partials.tabla_datos', [
                                'filas' => $filas,
                                'totales' => $resultado['totales'] ?? [],
                                'puede_ver_recepcion' => $puede_ver_recepcion ?? false,
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'tabla_ancha' => true,
                            ])
                        </div>
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="px-3 py-2">
                            Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
