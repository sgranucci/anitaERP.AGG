@extends("theme.$theme.layout")

@section('titulo')
    Reporte analítico gastronomía
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/gastronomia/analitico_reporte_filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/gastronomia/analitico_reporte_filtro.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte anal&iacute;tico gastronom&iacute;a</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tiene_filtros_texto ?? false,
                        'limpiarUrl' => route('gastronomia_analitico_reporte'),
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-analitico-gastro',
                        'formId' => 'form-analitico-gastro-reporte',
                        'inputId' => 'filtro_valor',
                    ])
                    <a href="{{ route('gastronomia_analitico_reporte') }}" class="btn btn-outline-secondary btn-sm ml-1" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>

            <form method="get" action="{{ route('gastronomia_analitico_reporte') }}" id="form-analitico-gastro-reporte" class="mb-0">
                @include('ventas.gastronomia.analitico_reporte.partials.filtros_listado')
            </form>

            @if ($consultado ?? false)
                <div class="card-body p-0 border-top">
                    <div class="px-3 py-2 border-bottom bg-light">
                        <p class="mb-0 small">
                            <strong>Empresa / sala:</strong> {{ $empresa_texto ?? '' }}
                            · <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                            @if (! empty($resultado))
                                · <strong>Lista costo:</strong> {{ $resultado['lista_costo'] ?? '' }}
                                · <strong>Filas:</strong> {{ (int) ($resultado['totales']['cantidad_filas'] ?? 0) }}
                            @endif
                        </p>
                        <p class="mb-0 small text-muted">
                            Datos desde anitaERP (venta / emisi&oacute;n gastronom&iacute;a). Costo lista 5000+mes.
                            Legajo mozo = c&oacute;digo del mozo.
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_gastronomia_analitico_reporte',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($resultado['totales']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Totales filtro:</span>
                                Filas <strong>{{ (int) ($resultado['totales']['cantidad_filas'] ?? 0) }}</strong>
                                · Cant.
                                <strong>{{ number_format((float) ($resultado['totales']['cantidad_total'] ?? 0), 3, ',', '.') }}</strong>
                                · Importe
                                <strong>${{ number_format((float) ($resultado['totales']['total_importe'] ?? 0), 2, ',', '.') }}</strong>
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
                        .tabla-analitico-gastro thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-analitico-gastro thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.75rem; }
                        .tabla-analitico-gastro td { font-size: 0.8rem; }
                    </style>

                    <div class="table-responsive">
                        <table id="tabla-paginada" class="table table-sm table-striped table-bordered table-hover mb-0 tabla-analitico-gastro">
                            @include('ventas.gastronomia.analitico_reporte.partials.tabla_datos', [
                                'filas' => $filas ?? [],
                                'con_links' => true,
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_mozo' => $puede_ver_mozo ?? false,
                                'puede_ver_factura' => $puede_ver_factura ?? false,
                                'puede_ver_cliente' => $puede_ver_cliente ?? false,
                                'puede_ver_descuento' => $puede_ver_descuento ?? false,
                                'puede_ver_puntoventa' => $puede_ver_puntoventa ?? false,
                                'puede_ver_categoria' => $puede_ver_categoria ?? false,
                                'puede_ver_tipotransaccion' => $puede_ver_tipotransaccion ?? false,
                                'puede_ver_empresa' => $puede_ver_empresa ?? false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                        <div class="card-footer py-2">
                            <div class="d-flex flex-wrap align-items-center justify-content-between">
                                <small class="text-muted">
                                    @if ($filas->total() > 0)
                                        Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }} registros
                                    @else
                                        Sin registros
                                    @endif
                                </small>
                                {{ $filas->links() }}
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
