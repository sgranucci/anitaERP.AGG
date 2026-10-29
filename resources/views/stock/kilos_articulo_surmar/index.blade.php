@extends("theme.$theme.layout")
@section('titulo')
    Kilos por artículo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Kilos por artículo</h3>
                <div class="card-tools ml-auto">
                    <a href="{{ route('kilos_articulo_surmar') }}" class="btn btn-outline-light btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('kilos_articulo_surmar') }}" id="form-kilos-articulo-surmar" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Totales de kilos por artículo para el informe mensual de SENASA.
                        Cubre los tres listados del sistema anterior: remito (REM), alta de producción (AP)
                        y recepción de mercadería (COM). Con «Los tres» salen juntos en la misma grilla.
                        La recepción suma el COM y las recepciones cargadas en anitaERP.
                    </p>
                    @if (! empty($error))
                        <div class="alert alert-warning">{{ $error }}</div>
                    @endif
                    @foreach ($advertencias ?? [] as $aviso)
                        <div class="alert alert-warning">{{ $aviso }}</div>
                    @endforeach
                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                    @endphp

                    <div class="form-group row">
                        <label for="fecha_desde" class="{{ $colLabel }} requerido">Desde</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" required
                                value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <label for="fecha_hasta" class="{{ $colLabel }} requerido">Hasta</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" required
                                value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="tipo" class="{{ $colLabel }}">Listado</label>
                        <div class="{{ $colInput }}">
                            <select name="tipo" id="tipo" class="form-control">
                                @foreach ($tipos as $valor => $etiqueta)
                                    <option value="{{ $valor }}" @selected(($filtros['tipo'] ?? 'todos') === $valor)>{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'kilos_desde',
                        'label' => 'Desde art&iacute;culo',
                        'inputName' => 'desde_articulo_id',
                        'codigoName' => 'desde_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['desde_sku'] ?? '',
                        'descripcion' => $descripcion_desde_sku ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'next_focus' => '#articulo_kilos_hasta_id_codigo',
                        'help' => 'Vacío = desde el primero. F1 o la lupa consultan; Enter resuelve el SKU.',
                    ])
                    @include('produccion.partials.campo_consulta_articulo', [
                        'prefix' => 'kilos_hasta',
                        'label' => 'Hasta art&iacute;culo',
                        'inputName' => 'hasta_articulo_id',
                        'codigoName' => 'hasta_sku',
                        'articuloId' => '',
                        'codigo' => $filtros['hasta_sku'] ?? '',
                        'descripcion' => $descripcion_hasta_sku ?? '',
                        'col_label' => $colLabel,
                        'col_input' => $colInput,
                        'next_focus' => '#fecha_desde',
                        'help' => 'Vacío = hasta el último. F1 o la lupa consultan; Enter resuelve el SKU.',
                    ])

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
                    <p class="text-muted small mb-2">{{ $subtitulo }}</p>
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_kilos_articulo_surmar',
                        'queryparams' => $filtrosQuery ?? [],
                        'variant' => 'compact',
                    ])
                    <div class="table-responsive mt-2">
                        <table id="tabla-kilos-articulo-surmar" class="table table-sm table-bordered table-hover mb-0">
                            @include('stock.kilos_articulo_surmar.partials.tabla_datos', [
                                'filas' => $filas,
                                'totales' => $totales,
                                'filtros' => $filtros,
                                'presentacion' => 'pantalla',
                                'puede_ver_articulo' => $puede_ver_articulo ?? false,
                            ])
                        </table>
                    </div>
                    @if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator && $filas->total() > 0)
                        <div class="mt-2">
                            Mostrando {{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}
                            {{ $filas->appends($filtrosQuery ?? [])->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'kilos-articulo-surmar-overlay',
    'tituloId' => 'kilos-articulo-surmar-titulo',
    'subtituloId' => 'kilos-articulo-surmar-subtitulo',
    'titulo' => 'Consultando kilos…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
@include('includes.stock.modalconsultaarticulo')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/articulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/kilos_articulo_surmar/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/kilos_articulo_surmar/filtro.js')) ?: time() }}"></script>
@endsection
