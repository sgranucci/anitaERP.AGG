@extends("theme.$theme.layout")

@section('titulo')
    Reportes Local — Ventas por artículo
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/puntoventa/consulta.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/reporte_ventas_articulos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/facturacion_local/reporte_ventas_articulos.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Reportes Local — Ventas por artículo</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('listar-facturas-facturacion-local', false))
                        <a href="{{ route('facturacion_local_facturas') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-file-text-o"></i> Facturas Local
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_reporte_costos') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-calculator"></i> Costos del local
                    </a>
                    @if (can('editar-facturacion-local-parametro', false))
                        <a href="{{ route('facturacion_local_parametros') }}" class="btn btn-outline-light btn-sm mr-1">
                            <i class="fa fa-cogs"></i> Parámetros
                        </a>
                    @endif
                    <a href="{{ route('facturacion_local_reportes') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_reportes') }}" id="form-fl-ventas-articulos" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Ventas netas del local y de Facturante (facturas menos notas de crédito) por artículo,
                        combinación/color y talle. Elija uno o varios puntos de venta, el rango de fechas y si abre por talle
                        o cierra por artículo/combinación. Importe bruto es el precio de lista; Descuento, el de la línea;
                        Importe venta, el neto cobrado. El tilde de costo agrega P.Vta., P.Costo e importe al costo
                        ({{ \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::etiquetaFormula() }}).
                        @if (can('editar-facturacion-local-parametro', false))
                            ·
                            <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">
                                Configurar listas fábrica y descuento
                            </a>
                        @endif
                    </p>

                    @include('ventas.partials.campo_consulta_puntoventa', [
                        'prefix' => 'fl_reporte',
                        'layout' => 'form_row',
                        'label' => 'Punto de venta',
                        'inputName' => 'puntoventa_borrador_id',
                        'inputId' => 'puntoventa_borrador_id',
                        'puntoventaId' => '',
                        'codigo' => '',
                        'nombre' => '',
                        'required' => false,
                        'col_label' => 'col-lg-2 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'mostrar_editar' => true,
                    ])
                    <div class="form-group row">
                        <div class="col-lg-6 offset-lg-2">
                            <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="btn-agregar-puntoventa">
                                <i class="fa fa-plus"></i> Agregar punto de venta
                            </button>
                            <div id="puntosventa-elegidos">
                                @foreach ($filtros['puntosventa'] ?? [] as $pv)
                                    <span class="badge badge-info mr-1 mb-1 fl-reporte-pv-chip">
                                        {{ $pv['codigo'] }}{{ ($pv['nombre'] ?? '') !== '' ? ' — '.$pv['nombre'] : '' }}
                                        <button type="button" class="btn btn-link btn-sm text-white p-0 ml-1 quitar-puntoventa-fl" title="Quitar">&times;</button>
                                        <input type="hidden" name="puntoventa_id[]" value="{{ $pv['id'] }}">
                                    </span>
                                @endforeach
                            </div>
                            <small class="form-text text-muted">Código y Enter, o la lupa. Podés pedir uno solo, o varios (por ejemplo 17 y 25).</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="col-lg-2 control-label text-right pr-2 requerido">Desde</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $filtros['fecha_desde'] ?? '' }}" required>
                        </div>
                        <label for="fecha_hasta" class="col-lg-2 control-label text-right pr-2 requerido">Hasta</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $filtros['fecha_hasta'] ?? '' }}" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="modo" class="col-lg-2 control-label text-right pr-2">Apertura</label>
                        <div class="col-lg-4">
                            <select name="modo" id="modo" class="form-control">
                                @foreach ($modos as $op)
                                    <option value="{{ $op['valor'] }}" @selected(($filtros['modo'] ?? '') === $op['valor'])>
                                        {{ $op['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-5">
                            <div class="custom-control custom-checkbox pt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_costo" name="incluir_costo" value="1"
                                    @checked(! empty($filtros['incluir_costo']))>
                                <label class="custom-control-label" for="incluir_costo">
                                    Incluir valorización al costo (P.Vta. / P.Costo)
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
                        <p class="mb-0 small">
                            @if (($resultado['nombreempresa'] ?? '') !== '')
                                <strong>Empresa:</strong> {{ $resultado['nombreempresa'] }}
                                ·
                            @endif
                            <strong>Punto de venta:</strong> {{ $resultado['puntoventa_texto'] ?? $resultado['local_texto'] ?? '' }}
                            · <strong>Período:</strong> {{ $periodo_texto ?? '' }}
                            · <strong>Modo:</strong> {{ $modo_texto ?? '' }}
                            @if (! empty($resultado['incluir_costo']))
                                · <strong>Costo:</strong> {{ $resultado['costo_formula'] ?? '' }}
                            @endif
                            @if (! empty($resultado))
                                · <strong>Líneas:</strong> {{ count($resultado['filas'] ?? []) }}
                            @endif
                        </p>
                        @if (! empty($resultado['incluir_costo']) && (int) ($resultado['sin_precio_fabrica'] ?? 0) > 0)
                            <p class="mb-0 small text-warning mt-1">
                                <i class="fa fa-exclamation-triangle"></i>
                                {{ (int) $resultado['sin_precio_fabrica'] }} línea(s) sin precio en listas fábrica
                                (códigos {{ implode(',', \App\Support\Ventas\FacturacionLocal\FacturacionLocalCostoFabricaSupport::codigosListasFabrica()) }}):
                                P.Costo queda en 0. Cargá el precio fábrica del artículo
                                @if (can('editar-facturacion-local-parametro', false))
                                    o revisá
                                    <a href="{{ route('facturacion_local_parametros') }}" class="text-primary">Parámetros Facturación Local</a>.
                                @else
                                    o pedí revisar Parámetros Facturación Local.
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_facturacion_local',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                        @if (! empty($resultado['totales']))
                            <div class="small mb-1 mb-md-0 text-md-right">
                                <span class="text-muted">Totales filtro:</span>
                                Cant. <strong>{{ number_format((float) ($resultado['totales']['cantidad'] ?? 0), 0, ',', '.') }}</strong>
                                · Imp. bruto
                                <strong>${{ number_format((float) ($resultado['totales']['importe_bruto'] ?? 0), 2, ',', '.') }}</strong>
                                · Descuento
                                <strong>${{ number_format((float) ($resultado['totales']['descuento'] ?? 0), 2, ',', '.') }}</strong>
                                · Imp. venta
                                <strong>${{ number_format((float) ($resultado['totales']['importe'] ?? 0), 2, ',', '.') }}</strong>
                                @if (! empty($resultado['incluir_costo']))
                                    · Imp. costo
                                    <strong>${{ number_format((float) ($resultado['totales']['importe_costo'] ?? 0), 2, ',', '.') }}</strong>
                                @endif
                            </div>
                        @endif
                    </div>

                    @php
                        $nombresEmpresaLogo = $resultado['nombres_empresa'] ?? [];
                        if ($nombresEmpresaLogo === [] && ! empty($resultado['nombreempresa'])) {
                            $nombresEmpresaLogo = [$resultado['nombreempresa']];
                        }
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect(array_map(
                                static fn (string $nombre) => (object) ['nombreempresa' => $nombre],
                                $nombresEmpresaLogo,
                            ))
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
                        .tabla-fl-ventas-articulos thead tr { background-color: #85C1E9; color: #17202A; }
                        .tabla-fl-ventas-articulos thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.85rem; }
                    </style>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered table-hover mb-0 tabla-fl-ventas-articulos" id="tabla-paginada">
                            @include('ventas.facturacion_local.reportes.partials.tabla_datos', [
                                'filas' => $filas_vista ?? [],
                                'totales' => $resultado['totales'] ?? [],
                                'abierto_talle' => $resultado['abierto_talle'] ?? true,
                                'incluir_costo' => $resultado['incluir_costo'] ?? false,
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
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'fl-reporte-overlay',
    'tituloId' => 'fl-reporte-overlay-titulo',
    'subtituloId' => 'fl-reporte-overlay-subtitulo',
    'titulo' => 'Consultando ventas…',
    'subtitulo' => 'Puede demorar según el período y los puntos de venta.',
])
@include('includes.ventas.modalconsultapuntoventa')
@endsection
