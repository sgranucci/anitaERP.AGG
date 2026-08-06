@extends("theme.$theme.layout")
@section('titulo')
    Historial de precios por art&iacute;culo/proveedor
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/historial_precios_articulo/filtro.js') }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'hpa-reporte-overlay',
    'tituloId' => 'hpa-reporte-overlay-titulo',
    'subtituloId' => 'hpa-reporte-overlay-subtitulo',
    'titulo' => 'Consultando historial de precios…',
    'subtitulo' => 'Puede demorar según el período y el volumen. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Historial de precios por art&iacute;culo/proveedor</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_historial_precios_articulo') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_historial_precios_articulo') }}" id="form-historial-precios-articulo" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Precios unitarios de recepciones de proveedor <strong>confirmadas</strong>.
                        En resumen muestra &uacute;ltimo precio, precio anterior, variaci&oacute;n %, proveedor y fecha de &uacute;ltima compra.
                        El detalle lista todas las compras del per&iacute;odo con la variaci&oacute;n respecto de la compra previa.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'historial_precios_articulo_reporte',
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
                            <small class="form-text text-muted">Por defecto &uacute;ltimos 12 meses. Vac&iacute;o = sin tope.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="modo" class="{{ $colLabel }}">Salida</label>
                        <div class="{{ $colInput }}">
                            <select name="modo" id="modo" class="form-control">
                                @foreach ($opciones_modo ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['modo'] ?? 'resumen') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="agrupacion" class="{{ $colLabel }}">Agrupar por</label>
                        <div class="{{ $colInput }}">
                            <select name="agrupacion" id="agrupacion" class="form-control">
                                @foreach ($opciones_agrupacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['agrupacion'] ?? 'articulo') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="articulo_id" class="{{ $colLabel }}">Art&iacute;culo ID</label>
                        <div class="{{ $colInput }}">
                            <input type="number" name="articulo_id" id="articulo_id" min="1"
                                class="form-control" style="max-width: 10rem;"
                                placeholder="Vac&iacute;o = todos"
                                value="{{ $filtros['articulo_id'] ?? '' }}">
                            <input type="text" class="form-control mt-1" readonly
                                value="{{ $meta_articulo ?? 'Todos los artículos' }}">
                        </div>
                        <label for="sku" class="{{ $colLabel }}">SKU contiene</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="sku" id="sku" class="form-control"
                                autocomplete="off"
                                value="{{ $filtros['sku'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedores" class="{{ $colLabel }}">Proveedores</label>
                        <div class="{{ $colInput }}">
                            <div id="hpa-reporte-proveedor-campo">
                                <div class="input-group mb-1">
                                    <input type="text" name="proveedores" id="proveedores" class="form-control codigoproveedor"
                                        placeholder="Vac&iacute;o = todos; c&oacute;digos separados por coma"
                                        title="F1 o lupa: consulta proveedores"
                                        autocomplete="off"
                                        value="{{ $filtros['proveedores'] ?? '' }}">
                                    <div class="input-group-append">
                                        <button type="button" title="Consulta proveedores (F1)"
                                            class="btn btn-outline-secondary consultaproveedor-hpa tooltipsC">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="text" class="form-control metaproveedor" readonly
                                    value="{{ $meta_proveedores ?? 'Todos los proveedores' }}">
                            </div>
                        </div>
                        <label class="{{ $colLabel }}">&nbsp;</label>
                        <div class="{{ $colInput }}">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="solo_con_variacion"
                                    name="solo_con_variacion" value="1"
                                    @checked(!empty($filtros['solo_con_variacion']))>
                                <label class="custom-control-label" for="solo_con_variacion">
                                    Solo art&iacute;culos / compras con variaci&oacute;n de precio
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="col-lg-2"></div>
                        <div class="col-lg-10">
                            <input type="hidden" name="consultar" value="1">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-consultar-historial-precios">
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
                            <strong>Per&iacute;odo:</strong> {{ $periodo_texto ?? '' }}
                            @if (! empty($resultado['totales']))
                                &middot; <strong>Art&iacute;culos:</strong> {{ (int) ($resultado['totales']['total_articulos'] ?? 0) }}
                                &middot; <strong>Filas:</strong> {{ (int) ($resultado['totales']['total_compras'] ?? 0) }}
                                &middot; <strong>Con variaci&oacute;n:</strong> {{ (int) ($resultado['totales']['con_variacion'] ?? 0) }}
                            @endif
                        </p>
                        @if (! empty($subtitulo))
                            <p class="mb-0 small text-muted">{{ $subtitulo }}</p>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_historial_precios_articulo',
                                'queryparams' => $filtrosQuery ?? [],
                            ])
                        </div>
                    </div>

                    @php
                        $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                            collect($filtros['empresa_ids'] ?? [])->map(function ($id) use ($empresa_query) {
                                $emp = ($empresa_query ?? collect())->firstWhere('id', (int) $id);

                                return $emp ? (object) ['nombreempresa' => $emp->nombre] : null;
                            })->filter()
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
                        #tabla-historial-precios-articulo thead tr { background-color: #85C1E9; color: #17202A; }
                        #tabla-historial-precios-articulo thead th { font-weight: 600; border-color: #7fb3d5; white-space: nowrap; font-size: 0.72rem; }
                        #tabla-historial-precios-articulo tbody td { font-size: 0.72rem; vertical-align: middle; }
                    </style>
                    <div class="table-responsive">
                        <table id="tabla-historial-precios-articulo" class="table table-striped table-bordered table-hover table-sm mb-0">
                            @include('compras.historial_precios_articulo.partials.tabla_datos', [
                                'filas' => $filasVista ?? [],
                                'modo' => $filtros['modo'] ?? 'resumen',
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                                'puede_ver_recepcion' => $puede_ver_recepcion ?? false,
                                'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                                'para_pdf' => false,
                            ])
                        </table>
                    </div>

                    @if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $filas->hasPages())
                        <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                            <span class="small text-muted mb-2 mb-md-0">
                                Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }} filas
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
@include('includes.compras.modalconsultaproveedor')
@endsection
