@extends("theme.$theme.layout")
@section('titulo')
    Proveedores con clientes de servicios
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor_servicio_reporte/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor_servicio_reporte/filtro.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'psr-overlay',
    'tituloId' => 'psr-overlay-titulo',
    'subtituloId' => 'psr-overlay-subtitulo',
    'titulo' => 'Consultando…',
    'subtitulo' => 'Puede demorar según el volumen. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Proveedores con clientes de servicios</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_proveedor_servicio') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_proveedor_servicio') }}" id="form-proveedor-servicio-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Proveedores que tienen cargado al menos un <strong>número de cliente / medidor</strong>
                        (luz, gas, agua, etc.) en la solapa «Servicios / medidores» del maestro.
                        Un renglón por cliente. Si el servicio no tiene empresa propia, se toma la del proveedor.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'reporte_clave' => 'proveedor_servicio_reporte',
                        'mostrar_consolidar' => false,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                    ])

                    <div class="form-group row">
                        <label for="proveedores" class="{{ $colLabel }}">Proveedores</label>
                        <div class="{{ $colInput }}">
                            <div id="psr-proveedor-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="proveedores" id="proveedores" class="form-control codigoproveedor"
                                        placeholder="Vacío = todos; códigos separados por coma"
                                        title="F1 o lupa: consulta proveedores"
                                        autocomplete="off"
                                        value="{{ $filtros['proveedores'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta proveedores (F1)"
                                            class="btn btn-outline-secondary consultaproveedor-psr tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metaproveedor" readonly
                                    value="{{ $meta_proveedores ?? 'Todos los proveedores' }}">
                            </div>
                        </div>
                        <label for="estado" class="{{ $colLabel }}">Estado del proveedor</label>
                        <div class="{{ $colInput }}">
                            <select name="estado" id="estado" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($estados ?? [] as $estado)
                                    <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="cliente" class="{{ $colLabel }}">Cliente / medidor contiene</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="cliente" id="cliente" class="form-control"
                                autocomplete="off" value="{{ $filtros['cliente'] ?? '' }}">
                        </div>
                        <label for="detalle" class="{{ $colLabel }}">Detalle contiene</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="detalle" id="detalle" class="form-control"
                                placeholder="Ej.: luz, gas, agua"
                                autocomplete="off" value="{{ $filtros['detalle'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-proveedor-servicio">
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
                            <strong>Proveedores:</strong> {{ (int) ($totales['proveedores'] ?? 0) }}
                            &middot; <strong>Clientes / medidores:</strong> {{ (int) ($totales['renglones'] ?? 0) }}
                        </p>
                        @if (! empty($subtitulo))
                            <p class="mb-0 small text-muted">{{ $subtitulo }}</p>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_proveedor_servicio',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filasVista ?? [])
                                ->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? ''])
                                ->filter(fn ($o) => trim((string) $o->nombreempresa) !== '')
                        );
                    @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        #tabla-paginada thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-paginada thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; }
                    </style>
                    <div class="tabla-ancha-grilla" data-tabla-ancha>
                        <div class="tabla-ancha-scroll-top" hidden>
                            <div class="tabla-ancha-scroll-top-inner"></div>
                        </div>
                        <div class="tabla-ancha-wrap">
                            <table id="tabla-paginada" class="table table-striped table-bordered table-hover table-sm mb-0 tabla-ancha">
                                @include('compras.proveedor_servicio_reporte.partials.tabla_datos', [
                                    'filas' => $filasVista ?? [],
                                    'para_pdf' => false,
                                    'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                ])
                            </table>
                        </div>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->total() > 0)
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@endsection
