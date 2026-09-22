@extends("theme.$theme.layout")
@section('titulo')
    Comisiones de vendedores — detalle
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/ventas/vendedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/vendedor/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/comision_vendedor/reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/comision_vendedor/reporte.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'comision-vendedor-overlay',
    'tituloId' => 'comision-vendedor-titulo',
    'subtituloId' => 'comision-vendedor-subtitulo',
    'titulo' => 'Consultando comisiones…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info comision-vendedor-card">
            <div class="card-header comision-vendedor-hero">
                <div class="d-flex flex-wrap align-items-center justify-content-between w-100">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fa fa-percentage mr-1"></i> Comisiones de vendedores
                        </h3>
                        <p class="mb-0 small mt-1 comision-vendedor-hero-sub">
                            Detalle por vendedor / cliente / factura (sin marca)
                        </p>
                    </div>
                    <div class="card-tools mt-2 mt-md-0">
                        <a href="{{ route($ruta_hermano ?? 'comision_vendedor', $filtrosQuery ?? []) }}"
                           class="btn btn-outline-light btn-sm mr-1" title="Ir al resumen por vendedor">
                            <i class="fa fa-compress-arrows-alt"></i> Ver resumen
                        </a>
                        <a href="{{ route($ruta_index ?? 'comision_vendedor_detalle') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                            <i class="fa fa-eraser"></i> Limpiar
                        </a>
                    </div>
                </div>
            </div>
            <form method="get" action="{{ route($ruta_index ?? 'comision_vendedor_detalle') }}" id="form-comision-vendedor" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Facturas y notas con vendedor asignado. La comisi&oacute;n usa el % de ventas del maestro
                        (<em>Sobre Neto</em> o <em>Sobre Bruto</em>). Las NC restan. Sin anulados ni presupuestos.
                    </p>
                    @include('ventas.comision_vendedor.partials.filtros_form', [
                        'filtros' => $filtros,
                        'empresa_query' => $empresa_query,
                        'tipo_query' => $tipo_query,
                        'puede_ver_vendedor' => $puede_ver_vendedor ?? false,
                    ])
                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar">
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
                            · <strong>Vendedor:</strong> {{ $vendedor_texto ?? 'Todos' }}
                            · <strong>Tipo:</strong> {{ $tipo_texto ?? 'Todos' }}
                        </p>
                    </div>

                    @if (! empty($totales))
                        <div class="comision-vendedor-kpis px-3 py-3">
                            <div class="row">
                                <div class="col-6 col-md-3 mb-2 mb-md-0">
                                    <div class="comision-kpi">
                                        <div class="comision-kpi-label">Comprobantes</div>
                                        <div class="comision-kpi-value">{{ (int) ($totales['cantidad_comprobantes'] ?? 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3 mb-2 mb-md-0">
                                    <div class="comision-kpi">
                                        <div class="comision-kpi-label">Vendedores</div>
                                        <div class="comision-kpi-value">{{ (int) ($totales['cantidad_vendedores'] ?? 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="comision-kpi">
                                        <div class="comision-kpi-label">Gravado</div>
                                        <div class="comision-kpi-value">{{ number_format((float) ($totales['gravado'] ?? 0), 2, ',', '.') }}</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <div class="comision-kpi comision-kpi-accent">
                                        <div class="comision-kpi-label">Comisi&oacute;n</div>
                                        <div class="comision-kpi-value">{{ number_format((float) ($totales['comision'] ?? 0), 2, ',', '.') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => $ruta_export ?? 'listar_comision_vendedor_detalle',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasVista ?? [])); @endphp
                    @if (count($logosVista) > 0)
                        <div class="border-bottom px-3 py-2 d-flex flex-wrap align-items-center">
                            @foreach ($logosVista as $logo)
                                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2 mb-1" style="max-height: 48px; max-width: 140px;">
                            @endforeach
                        </div>
                    @endif

                    <style>
                        .comision-vendedor-hero {
                            background: linear-gradient(120deg, #1B4F72 0%, #2471A3 45%, #5DADE2 100%) !important;
                            border-bottom: 0;
                        }
                        .comision-vendedor-hero .card-title,
                        .comision-vendedor-hero-sub { color: #fff !important; }
                        .comision-vendedor-kpis { background: #F4F9FC; border-bottom: 1px solid #d6eaf8; }
                        .comision-kpi {
                            background: #fff;
                            border: 1px solid #d6eaf8;
                            border-radius: 8px;
                            padding: 0.65rem 0.85rem;
                            height: 100%;
                            box-shadow: 0 1px 2px rgba(27, 79, 114, 0.06);
                        }
                        .comision-kpi-accent {
                            border-color: #85C1E9;
                            background: linear-gradient(180deg, #EBF5FB 0%, #fff 100%);
                        }
                        .comision-kpi-label {
                            font-size: 0.7rem;
                            text-transform: uppercase;
                            letter-spacing: 0.04em;
                            color: #5D6D7E;
                            font-weight: 600;
                        }
                        .comision-kpi-value {
                            font-size: 1.15rem;
                            font-weight: 700;
                            color: #1B4F72;
                            line-height: 1.3;
                        }
                        #tabla-comision-vendedor thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-comision-vendedor thead th { font-weight: 600; border-color: #7fb3d5; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-comision-vendedor" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.comision_vendedor_detalle.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'puede_ver_venta' => $puede_ver_venta ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_vendedor' => $puede_ver_vendedor ?? false,
                            ])
                        </table>
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
                            {{ $filas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultavendedor')
@endsection
