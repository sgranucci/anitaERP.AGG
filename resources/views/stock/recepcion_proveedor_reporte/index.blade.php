@extends("theme.$theme.layout")
@section('titulo')
    Recepci&oacute;n de proveedores
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/reportes/empresas_checkboxes.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor_reporte/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor_reporte/filtro.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'rpr-overlay',
    'tituloId' => 'rpr-overlay-titulo',
    'subtituloId' => 'rpr-overlay-subtitulo',
    'titulo' => 'Consultando recepciones…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<style>
    #rpr-overlay.d-none { display: none !important; }
    .rpr-tabla thead th { white-space: nowrap; }
    .rpr-tabla td { white-space: nowrap; }
    .rpr-header-empresa td { background: #1B4F72; color: #fff; font-weight: 600; }
    .rpr-header-grupo td { background: #D6EAF8; color: #1B4F72; }
    .rpr-subtotal td { background: #F4F6F7; font-weight: 600; }
</style>
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Informe de recepci&oacute;n de proveedores</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_recepcion_proveedor') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_recepcion_proveedor') }}" class="mb-0" id="form-recepcion-proveedor-reporte">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Movimientos de mercader&iacute;a recibida de proveedores (COM), con cruce contra OC, requisici&oacute;n,
                        factura ERP y diferencias de cantidad/precio.
                        El per&iacute;odo por defecto es de 90 d&iacute;as.
                        Las filas en amarillo tienen diferencias; en celeste, precio pendiente de aprobaci&oacute;n.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'recepcion_proveedor_reporte',
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
                        <label for="modo" class="{{ $colLabel }}">Salida</label>
                        <div class="{{ $colInput }}">
                            <select name="modo" id="modo" class="form-control">
                                @foreach ($opciones_modo ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['modo'] ?? 'detalle') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="orden" class="{{ $colLabel }}">Orden</label>
                        <div class="{{ $colInput }}">
                            <select name="orden" id="orden" class="form-control">
                                @foreach ($opciones_orden ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['orden'] ?? 'fecha') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="facturacion" class="{{ $colLabel }}">Facturaci&oacute;n ERP</label>
                        <div class="{{ $colInput }}">
                            <select name="facturacion" id="facturacion" class="form-control">
                                @foreach ($opciones_facturacion ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['facturacion'] ?? 'todas') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="tipo" class="{{ $colLabel }}">Tipo</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo" id="tipo" class="form-control">
                                @foreach ($opciones_tipo ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['tipo'] ?? 'todas') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="estado" class="{{ $colLabel }}">Estado COM</label>
                        <div class="{{ $colInput }}">
                            <select name="estado" id="estado" class="form-control">
                                @foreach ($opciones_estado ?? [] as $opcion)
                                    <option value="{{ $opcion['valor'] }}" @selected(($filtros['estado'] ?? 'CONFIRMADA') === $opcion['valor'])>
                                        {{ $opcion['etiqueta'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label class="{{ $colLabel }}">Restricciones</label>
                        <div class="{{ $colInput }} pt-1">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="solo_diferencias" id="solo_diferencias" value="1"
                                    @checked(! empty($filtros['solo_diferencias']))>
                                <label class="custom-control-label" for="solo_diferencias">Solo con diferencias</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="solo_rechazadas" id="solo_rechazadas" value="1"
                                    @checked(! empty($filtros['solo_rechazadas']))>
                                <label class="custom-control-label" for="solo_rechazadas">Solo l&iacute;neas rechazadas</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="proveedor" class="{{ $colLabel }}">Proveedor</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="proveedor" id="proveedor" class="form-control"
                                placeholder="C&oacute;digo o nombre"
                                value="{{ $filtros['proveedor'] ?? '' }}">
                        </div>
                        <label for="sku" class="{{ $colLabel }}">Art&iacute;culo</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="sku" id="sku" class="form-control"
                                placeholder="SKU o descripci&oacute;n"
                                value="{{ $filtros['sku'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="deposito" class="{{ $colLabel }}">Dep&oacute;sito</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="deposito" id="deposito" class="form-control"
                                placeholder="C&oacute;digo o nombre"
                                value="{{ $filtros['deposito'] ?? '' }}">
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
                    </div>

                    @include('stock.recepcion_proveedor_reporte.partials.kpis', [
                        'kpis' => $resultado['kpis'] ?? [],
                    ])

                    @if (! empty($resultado['advertencia_cotizacion']))
                        <div class="px-3 py-2 border-bottom">
                            <div class="alert alert-warning mb-0 py-2">
                                {{ $resultado['advertencia_cotizacion'] }}
                            </div>
                        </div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 border-bottom bg-light">
                        <div class="mb-1 mb-md-0">
                            @include('includes.exportar-tabla-queryparams', [
                                'ruta' => 'listar_reporte_recepcion_proveedor',
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

                    <div class="table-responsive">
                        @include('stock.recepcion_proveedor_reporte.partials.tabla_datos', [
                            'filas' => $filas,
                            'modo' => $filtros['modo'] ?? 'detalle',
                            'columnas_completas' => true,
                            'puede_ver_recepcion' => $puede_ver_recepcion ?? false,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            'puede_ver_ordencompra' => $puede_ver_ordencompra ?? false,
                            'puede_ver_requisicion' => $puede_ver_requisicion ?? false,
                            'puede_ver_proveedor' => $puede_ver_proveedor ?? false,
                            'puede_ver_cuentacontable' => $puede_ver_cuentacontable ?? false,
                            'puede_ver_comprobante' => $puede_ver_comprobante ?? false,
                        ])
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
