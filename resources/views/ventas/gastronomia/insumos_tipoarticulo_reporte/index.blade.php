@extends("theme.$theme.layout")
@section('titulo')
    Ventas insumos gastronomía por día
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ventas insumos gastronomía por día</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_insumos_tipoarticulo_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_insumos_tipoarticulo_reporte') }}" id="form-insumos-tipoarticulo" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cantidades facturadas en gastronomía por insumo (tipo de artículo) y día de jornada.
                        Por defecto filtra el tipo con control contable de cigarrillos; puede cambiarlo en pantalla.
                        Si el tipo tiene el flag habilitado, se muestra la conciliación Contaduría vs mayor Anita.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row">
                        <label for="tipoarticulo_id" class="col-lg-2 control-label text-right pr-2 requerido">Tipo artículo</label>
                        <div class="col-lg-4">
                            <select name="tipoarticulo_id" id="tipoarticulo_id" class="form-control" required>
                                @foreach ($tipoarticulo_query as $tipo)
                                    <option value="{{ $tipo->id }}" @selected((int) ($filtros['tipoarticulo_id'] ?? 0) === (int) $tipo->id)>
                                        {{ $tipo->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

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
                            <strong>Tipo:</strong> {{ $tipoarticulo_etiqueta ?? '' }}
                            · <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                            · <strong>Período jornada:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Artículos con venta:</strong> {{ (int) ($resultado['cantidad_articulos'] ?? 0) }}
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_insumos_tipoarticulo',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($resultado))
                            @php
                                $unidadMedidaEtiqueta = trim((string) ($resultado['unidad_medida_etiqueta'] ?? 'unidades'));
                                if ($unidadMedidaEtiqueta === '') {
                                    $unidadMedidaEtiqueta = 'unidades';
                                }
                            @endphp
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Total general:</span>
                                <strong>{{ number_format((float) ($resultado['total_general'] ?? 0), 3, ',', '.') }}</strong>
                                <span class="text-muted">{{ $unidadMedidaEtiqueta }}</span>
                            </div>
                        @endif
                    </div>

                    @if (! empty($usa_control_contable_cigarrillos) && ! empty($control_contable))
                        @include('ventas.gastronomia.insumos_tipoarticulo_reporte.partials.control_contable_cigarrillos', [
                            'control_contable' => $control_contable,
                            'filtrosQuery' => $filtrosQuery ?? [],
                        ])
                    @endif

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
                        #tabla-insumos-tipoarticulo thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-insumos-tipoarticulo thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-insumos-tipoarticulo" class="table table-striped table-bordered table-hover table-sm mb-0" style="font-size: 0.78rem;">
                            @include('ventas.gastronomia.insumos_tipoarticulo_reporte.partials.tabla_datos', [
                                'resultado' => $resultado,
                                'filas' => $filasVista ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->hasPages())
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} filas
                            </span>
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @elseif ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer">
                            <small class="text-muted">{{ $filas->total() }} fila(s).</small>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
