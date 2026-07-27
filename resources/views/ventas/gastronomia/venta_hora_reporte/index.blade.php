@extends("theme.$theme.layout")

@section('titulo')
    Venta hora por hora
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/venta_hora_reporte/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/venta_hora_reporte/index.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Venta hora por hora</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_venta_hora_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('gastronomia_venta_hora_reporte') }}" id="form-venta-hora-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Total facturado por jornada y hora de emisi&oacute;n. Las notas de cr&eacute;dito se muestran restando.
                        Las horas siguen el orden operativo de gastronom&iacute;a: 07 a 23 y 00 a 06.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta jornada</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="hora_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde hora</label>
                        <div class="col-lg-3">
                            <input type="number" name="hora_desde" id="hora_desde" class="form-control"
                                min="0" max="23" step="1"
                                value="{{ $filtros['hora_desde'] ?? 0 }}" required>
                        </div>
                        <label for="hora_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta hora</label>
                        <div class="col-lg-3">
                            <input type="number" name="hora_hasta" id="hora_hasta" class="form-control"
                                min="0" max="24" step="1"
                                value="{{ $filtros['hora_hasta'] ?? 24 }}" required>
                            <small class="form-text text-muted">Default 0 a 24 (todas las horas). 24 incluye hasta las 23.</small>
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
                    <div class="px-3 py-2 border-bottom bg-light small">
                        <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                        · <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                        · <strong>Horas:</strong> {{ $resultado['rango_horas_texto'] ?? '' }}
                        · <strong>Jornadas:</strong> {{ $resultado['cantidad_dias'] ?? 0 }}
                        · <strong>Comprobantes:</strong> {{ $resultado['cantidad_comprobantes'] ?? 0 }}
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_venta_hora_reporte',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        <div class="small mb-1 mb-md-0 text-md-right">
                            <span class="text-muted">Total filtro:</span>
                            <strong>${{ number_format((float) ($resultado['total_general'] ?? 0), 2, ',', '.') }}</strong>
                            · <span class="text-muted">Promedio por hora:</span>
                            <strong>${{ number_format((float) ($resultado['promedio_hora'] ?? 0), 2, ',', '.') }}</strong>
                        </div>
                    </div>

                    @php
                        $empresaLogo = ($empresa_query ?? collect())->firstWhere('id', (int) ($filtros['empresa_id'] ?? 0));
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            $empresaLogo
                                ? collect([(object) ['nombreempresa' => $empresaLogo->nombre]])
                                : collect()
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
                        .tabla-venta-hora thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-venta-hora th, .tabla-venta-hora td { white-space: nowrap; font-size: .8rem; }
                        .tabla-venta-hora th:nth-child(1), .tabla-venta-hora td:nth-child(1) { min-width: 48px; }
                        .tabla-venta-hora th:nth-child(2), .tabla-venta-hora td:nth-child(2) { min-width: 92px; }
                        .tabla-venta-hora th:nth-child(n+3), .tabla-venta-hora td:nth-child(n+3) { min-width: 92px; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-sm table-striped table-bordered table-hover mb-0 tabla-venta-hora">
                            @include('ventas.gastronomia.venta_hora_reporte.partials.tabla_datos', [
                                'filas' => $filas_vista ?? [],
                                'horas' => $resultado['horas'] ?? [],
                                'totales_hora' => $resultado['totales_hora'] ?? [],
                                'total_general' => $resultado['total_general'] ?? 0,
                                'promedio_hora' => $resultado['promedio_hora'] ?? 0,
                                'mostrar_totales' => true,
                            ])
                        </table>
                    </div>

                    @if ($filas_pag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    Jornadas {{ $filas_pag->firstItem() }}–{{ $filas_pag->lastItem() }}
                                    de {{ $filas_pag->total() }}
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

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'venta-hora-overlay',
    'tituloId' => 'venta-hora-overlay-titulo',
    'subtituloId' => 'venta-hora-overlay-subtitulo',
    'titulo' => 'Calculando venta hora por hora…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@endsection
