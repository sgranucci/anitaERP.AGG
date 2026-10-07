@extends("theme.$theme.layout")

@section('titulo')
    Reporte de costos del local
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/mventa/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/reporte_costos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/reporte_costos.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Reporte de costos del local</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('reportes-facturacion-local', false))
                        <a href="{{ route('facturacion_local_reportes') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-chart-bar"></i> Ventas por artículo
                        </a>
                    @endif
                    @if (can('editar-facturacion-local-parametro', false))
                        <a href="{{ route('facturacion_local_parametros') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-cogs"></i> Parámetros
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_reporte_costos') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_reporte_costos') }}" id="form-fl-costos-local" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Costo de cada SKU = precio de fábrica vigente × el factor de
                        <strong>Parámetros Facturación Local</strong>
                        ({{ \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::etiquetaFormula() }}).
                        Los accesorios que no vende fábrica quedan con precio en 0 y el listado los marca.
                        @if (can('editar-facturacion-local-parametro', false))
                            <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">Configurar el factor</a>.
                        @endif
                    </p>

                    @include('stock.partials.campo_consulta_mventa', [
                        'prefix' => 'fl_costos',
                        'layout' => 'form_row',
                        'label' => 'Marca',
                        'inputName' => 'mventa_id',
                        'inputId' => 'mventa_id',
                        'mventaId' => ($filtros['mventa_id'] ?? 0) > 0 ? $filtros['mventa_id'] : '',
                        'codigo' => $filtros['mventa_codigo'] ?? '',
                        'nombre' => $filtros['mventa_nombre'] ?? '',
                        'required' => false,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'mostrar_editar' => true,
                    ])

                    <div class="form-group row">
                        <label for="filtro_canal" class="col-lg-2 control-label text-right pr-2">Canal</label>
                        <div class="col-lg-3">
                            <select name="filtro_canal" id="filtro_canal" class="form-control">
                                <option value="TODOS" @selected(($filtros['canal'] ?? '') === 'TODOS')>Fábrica y local</option>
                                <option value="FABRICA" @selected(($filtros['canal'] ?? '') === 'FABRICA')>Fábrica</option>
                                <option value="LOCAL" @selected(($filtros['canal'] ?? '') === 'LOCAL')>Local</option>
                            </select>
                        </div>
                        <label for="filtro_estado" class="col-lg-2 control-label text-right pr-2">SKU</label>
                        <div class="col-lg-3">
                            <select name="filtro_estado" id="filtro_estado" class="form-control">
                                <option value="ACTIVOS" @selected(($filtros['estado'] ?? 'ACTIVOS') === 'ACTIVOS')>Activos</option>
                                <option value="INACTIVOS" @selected(($filtros['estado'] ?? '') === 'INACTIVOS')>Inactivos</option>
                                <option value="TODOS" @selected(($filtros['estado'] ?? '') === 'TODOS')>Activos e inactivos</option>
                            </select>
                            <small class="form-text text-muted">
                                Con canal Fábrica o Local, el estado es el de ese ámbito.
                            </small>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="solo_precio_cero" name="solo_precio_cero" value="1"
                                    @checked(! empty($filtros['solo_precio_cero']))>
                                <label class="custom-control-label" for="solo_precio_cero">
                                    Solo SKU con precio de fábrica en 0
                                </label>
                            </div>
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
                        <p class="mb-0 small">
                            <strong>{{ $resultado['texto_filtros'] ?? '' }}</strong>
                            · Vigencia {{ \Carbon\Carbon::parse($resultado['fecha_vigencia'] ?? now())->format('d/m/Y') }}
                            · {{ $resultado['costo_formula'] ?? '' }}
                            · <strong>SKU del filtro:</strong> {{ (int) ($resultado['totales']['sku'] ?? 0) }}
                            · Con precio: {{ (int) ($resultado['totales']['con_precio'] ?? 0) }}
                            · Sin precio: {{ (int) ($resultado['sin_precio'] ?? 0) }}
                        </p>
                        @if ((int) ($resultado['sin_precio'] ?? 0) > 0)
                            <div class="alert alert-warning py-2 px-3 mt-2 mb-0">
                                <i class="fa fa-exclamation-triangle"></i>
                                Hay <strong>{{ (int) $resultado['sin_precio'] }}</strong>
                                SKU con precio de fábrica en 0 (el costo local queda en 0).
                                Suele pasar con accesorios que fábrica no vende.
                                @if (empty($filtros['solo_precio_cero']))
                                    @php
                                        $qsSoloCero = array_merge($filtrosQuery ?? [], ['solo_precio_cero' => 1, 'consultar' => 1]);
                                        unset($qsSoloCero['page']);
                                    @endphp
                                    <a class="alert-link" href="{{ route('facturacion_local_reporte_costos', $qsSoloCero) }}">Ver solo esos SKU</a>.
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_facturacion_local_costos',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        .tabla-fl-costos-local thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-fl-costos-local thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.85rem; }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover mb-0 tabla-fl-costos-local" id="tabla-paginada">
                            @include('ventas.facturacion_local.reporte_costos.partials.tabla_datos', [
                                'filas' => $filas_vista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                        </table>
                    </div>
                    @if ($filas_pag ?? null)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    @if ($filas_pag->total() > 0)
                                        Mostrando {{ $filas_pag->firstItem() }}–{{ $filas_pag->lastItem() }}
                                        de {{ $filas_pag->total() }}
                                    @endif
                                </small>
                                {{ $filas_pag->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.stock.modalconsultamventa')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'fl-costos-overlay',
    'tituloId' => 'fl-costos-titulo',
    'subtituloId' => 'fl-costos-subtitulo',
    'titulo' => 'Calculando costos…',
    'subtitulo' => 'Puede demorar según la cantidad de SKU. No cierre la página.',
])
@endsection
