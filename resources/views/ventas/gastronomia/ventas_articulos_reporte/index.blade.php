@extends("theme.$theme.layout")

@section('titulo')
    Ventas de artículos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Ventas de artículos</h3>
                <div class="card-tools">
                    <a href="{{ route('gastronomia_ventas_articulos_reporte') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('gastronomia_ventas_articulos_reporte') }}" id="form-ventas-articulos-reporte" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Ventas facturadas en gastronom&iacute;a por art&iacute;culo de venta (excluye insumos
                        <em>INSUMO GASTRONOMIA</em>): externas, invitaciones y staff seg&uacute;n tipo consumo del descuento.
                        P.Vta. lista {{ (int) config('precio.listaprecio_default_id', 1) }};
                        costo lista {{ (int) config('gastronomia.informe_gerente_costo_lista_base', 5000) }} + mes.
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
                        <p class="mb-0 small">
                            <strong>Empresa:</strong> {{ $empresa_texto ?? '' }}
                            · <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                            @if (! empty($resultado))
                                · <strong>P.Vta. lista:</strong> {{ $resultado['listaprecio_venta_codigo'] ?? '' }}
                                · <strong>Lista costo:</strong> {{ $resultado['listas_costo']['lista_actual'] ?? '' }}
                                ({{ $resultado['listas_costo']['mes_actual_label'] ?? '' }})
                                · <strong>Art&iacute;culos:</strong> {{ count($resultado['filas'] ?? []) }}
                            @endif
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_ventas_articulos_reporte',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($resultado['totales']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Totales filtro:</span>
                                Cant. <strong>{{ number_format((float) ($resultado['totales']['cant_total'] ?? 0), 0, ',', '.') }}</strong>
                                · Imp. externa
                                <strong>${{ number_format((float) ($resultado['totales']['importe_externa'] ?? 0), 2, ',', '.') }}</strong>
                            </div>
                        @endif
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
                        .tabla-ventas-articulos-reporte thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-ventas-articulos-reporte thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.85rem; }
                    </style>

                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-sm table-striped table-bordered table-hover mb-0 tabla-ventas-articulos-reporte">
                            @include('ventas.gastronomia.ventas_articulos_reporte.partials.tabla_datos', [
                                'filas' => $filas_vista ?? [],
                                'totales' => $resultado['totales'] ?? [],
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas_pag instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    Art&iacute;culos {{ $filas_pag->firstItem() }}–{{ $filas_pag->lastItem() }}
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
@endsection
