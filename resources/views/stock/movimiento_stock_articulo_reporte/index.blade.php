@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de stock por artículo
@endsection

@section('styles')
<style>
    .stkmov-tabla { font-size: 12px; }
    .stkmov-tabla th, .stkmov-tabla td { white-space: nowrap; padding: 3px 5px; }
    .stkmov-tabla td:last-child { white-space: normal; min-width: 12rem; }
</style>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Movimientos de stock por artículo</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('reporte_movimientos_stock_articulo') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_movimientos_stock_articulo') }}" id="form-stkmov-articulo" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Equivalente Anita <code>l-stkmov.c</code>, orden por código de artículo.
                        Cada talle es un renglón. La combinación (color) es lo que Anita imprimía en N.Par.
                        El importe es el precio del movimiento menos el descuento de la venta.
                    </p>
                    @if (! empty($error))
                        <div class="alert alert-warning">{{ $error }}</div>
                    @endif
                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $colDep = 'col-lg-2';
                    @endphp

                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'stkmov_desde',
                        'label' => 'Desde art&iacute;culo',
                        'inputName' => 'desde_articulo_id',
                        'codigoName' => 'desde_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['desde_sku'] ?? '',
                        'descripcion' => $descripcion_desde_sku ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'next_focus' => '#articulo_stkmov_hasta_id_codigo',
                        'help' => 'Vacío = desde el primero. F1 o lupa consulta; Enter resuelve el SKU.',
                    ])
                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'stkmov_hasta',
                        'label' => 'Hasta art&iacute;culo',
                        'inputName' => 'hasta_articulo_id',
                        'codigoName' => 'hasta_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['hasta_sku'] ?? '',
                        'descripcion' => $descripcion_hasta_sku ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'help' => 'Vacío = hasta el último.',
                    ])

                    <div class="form-group row">
                        <label for="desde_combinacion" class="{{ $colLabel }}">Desde combinación</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="desde_combinacion" id="desde_combinacion" class="form-control"
                                value="{{ $filtros['desde_combinacion'] ?? '' }}" autocomplete="off" placeholder="Código">
                            <small class="form-text text-muted">Código de combinación (color). Vacío = todas.</small>
                        </div>
                        <label for="hasta_combinacion" class="{{ $colLabel }}">Hasta combinación</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="hasta_combinacion" id="hasta_combinacion" class="form-control"
                                value="{{ $filtros['hasta_combinacion'] ?? '' }}" autocomplete="off" placeholder="Código">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }}">Hasta fecha</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'stkmov_desde',
                        'layout' => 'form_row',
                        'label' => 'Desde depósito',
                        'inputName' => 'desde_deposito_id',
                        'codigoName' => 'desde_deposito',
                        'depositoId' => '',
                        'codigo' => $filtros['desde_deposito'] ?? '',
                        'descripcion' => $descripcion_desde_deposito ?? '',
                        'required' => false,
                        'col_label' => $colDep,
                        'col_input' => $colInput,
                    ])
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'stkmov_hasta',
                        'layout' => 'form_row',
                        'label' => 'Hasta depósito',
                        'inputName' => 'hasta_deposito_id',
                        'codigoName' => 'hasta_deposito',
                        'depositoId' => '',
                        'codigo' => $filtros['hasta_deposito'] ?? '',
                        'descripcion' => $descripcion_hasta_deposito ?? '',
                        'required' => false,
                        'col_label' => $colDep,
                        'col_input' => $colInput,
                    ])

                    <div class="form-group row">
                        <label for="tipos" class="{{ $colLabel }}">Comprobantes</label>
                        <div class="{{ $colInput }}">
                            <input type="text" name="tipos" id="tipos" class="form-control"
                                value="{{ $filtros['tipos'] ?? '' }}" placeholder="FAC, NCD" autocomplete="off">
                            <small class="form-text text-muted">Vacío = todos. Abreviaturas separadas por coma.</small>
                        </div>
                        <label for="modo" class="{{ $colLabel }}">Detalle</label>
                        <div class="{{ $colInput }}">
                            <select name="modo" id="modo" class="form-control">
                                <option value="movimientos" @selected(($filtros['modo'] ?? 'movimientos') === 'movimientos')>Movimientos</option>
                                <option value="totales" @selected(($filtros['modo'] ?? '') === 'totales')>Totales solamente</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Total por día</label>
                        <div class="{{ $colInput }} pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="total_dia" name="total_dia" value="1"
                                    @checked(! empty($filtros['total_dia']))>
                                <label class="custom-control-label" for="total_dia">Imprimir total del día</label>
                            </div>
                        </div>
                        <label class="{{ $colLabel }}">Salto por artículo</label>
                        <div class="{{ $colInput }} pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="salto_articulo" name="salto_articulo" value="1"
                                    @checked(! empty($filtros['salto_articulo']))>
                                <label class="custom-control-label" for="salto_articulo">Salto de hoja en el PDF</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <div class="{{ $colLabel }}"></div>
                        <div class="col-lg-10">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            @if ($consultado)
                <div class="card-body pt-0">
                    @if ($totales)
                        <div class="mb-2">
                            <span class="badge badge-info mr-1">Artículos: {{ (int) ($totales['articulos'] ?? 0) }}</span>
                            <span class="badge badge-secondary mr-1">Movimientos: {{ (int) ($totales['movimientos'] ?? 0) }}</span>
                            <span class="badge badge-success mr-1">Entrada: {{ number_format((float) ($totales['entrada'] ?? 0), 2, ',', '.') }}</span>
                            <span class="badge badge-warning mr-1">Salida: {{ number_format((float) ($totales['salida'] ?? 0), 2, ',', '.') }}</span>
                            <span class="badge badge-dark">Importe: {{ number_format((float) ($totales['importe'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                    @endif

                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_reporte_movimientos_stock_articulo',
                        'queryparams' => $filtrosQuery ?? [],
                        'variant' => 'compact',
                    ])

                    <div class="table-responsive mt-2">
                        @include('stock.movimiento_stock_articulo_reporte.partials.tabla_datos', [
                            'filas' => $filas,
                            'totales' => $totales,
                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                        ])
                    </div>

                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}&ndash;{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'stkmov-articulo-overlay',
    'tituloId' => 'stkmov-articulo-titulo',
    'subtituloId' => 'stkmov-articulo-subtitulo',
    'titulo' => 'Consultando movimientos…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimiento_stock_articulo_reporte/reporte.js') }}?v={{ filemtime(public_path('assets/pages/scripts/stock/movimiento_stock_articulo_reporte/reporte.js')) }}"></script>
@endsection
