@extends("theme.$theme.layout")
@section('titulo')
    Entregas de indumentaria
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
@endsection

@section('scripts')
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $empleadoWorkbenchJs = public_path('assets/pages/scripts/sueldos/empleado/workbench.js');
    $columnasDndJs = public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js');
    $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/workbench.js') }}?v={{ file_exists($empleadoWorkbenchJs) ? filemtime($empleadoWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ file_exists($columnasDndJs) ? filemtime($columnasDndJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
@if (($graficoPorPrenda['total'] ?? 0) > 0)
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script>
(function () {
    var datos = @json($graficoPorPrenda ?? []);
    var grafico = null;
    function dibujar() {
        var canvas = document.getElementById('entrega-grafico-prenda');
        if (!canvas || typeof Chart === 'undefined' || !datos.labels || !datos.labels.length) {
            return;
        }
        if (!grafico) {
            // Barras = líneas (legible). Unidades solo en el tip: un outlier de unidades
            // (p.ej. Guantes Latex 60.919) aplastaba el resto de la escala.
            var serie = (datos.serie_principal === 'lineas' && datos.lineas)
                ? datos.lineas
                : datos.cantidades;
            grafico = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: datos.labels,
                    datasets: [{
                        label: 'Líneas',
                        data: serie,
                        backgroundColor: '#85C1E9',
                        borderColor: '#2471A3',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        callbacks: {
                            label: function (item) {
                                var i = item.index;
                                var cant = datos.cantidades[i] || 0;
                                var lineas = datos.lineas[i] || 0;
                                return lineas.toLocaleString('es-AR') + ' líneas · '
                                    + cant.toLocaleString('es-AR', { maximumFractionDigits: 3 }) + ' unidades';
                            }
                        }
                    },
                    scales: {
                        yAxes: [{
                            ticks: {
                                beginAtZero: true,
                                callback: function (v) { return Number.isInteger(v) ? v : ''; }
                            },
                            scaleLabel: { display: true, labelString: 'Líneas de entrega' }
                        }],
                        xAxes: [{ ticks: { autoSkip: false, maxRotation: 45, minRotation: 25 } }]
                    }
                }
            });
            return;
        }
        grafico.resize();
    }
    var panel = document.getElementById('entrega-grafico-body');
    if (panel && window.jQuery) {
        window.jQuery(panel).on('shown.bs.collapse', dibujar);
    }
})();
</script>
@endif
@endsection

@php
    use App\Support\Sueldos\EntregaPrendaListadoFiltros;
    $columnasVisibles = $columnasVisibles ?? [];
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    // Conserva empresa (chip) y vista; limpia año, fechas, agrupamiento, texto y QBE.
    $limpiarQ = ['filtro_limpiar' => 1, 'consultar' => 1, 'anio' => ''];
    if (($filtros['empresa_scope'] ?? '') === 'todas') {
        $limpiarQ['empresa_todas'] = 1;
    } elseif (! empty($filtros['empresa_id'])) {
        $limpiarQ['empresa_id'] = (int) $filtros['empresa_id'];
    } else {
        $limpiarQ['empresa_todas'] = 1;
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarUrl = route('entrega_prenda_reporte', $limpiarQ);
    $camposOrdenablesTheadBarra = \App\Support\Sueldos\EntregaPrendaListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesTheadBarra
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort'], $qsQuitarOrden['page']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('entrega_prenda_reporte', $qsQuitarOrden);

    $hayTexto = trim((string) ($filtros['valor'] ?? '')) !== '' || $qbeAbierto;
    $hayFiltrosExternos = ! empty($filtros['anio'])
        || trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
        || trim((string) ($filtros['fecha_hasta'] ?? '')) !== ''
        || ! empty($filtros['agrupamiento_id']);
    $mostrarLimpiar = $hayTexto || $hayFiltrosExternos;
    $puedeVerEmpleado = can('actualizar-empleado-sueldos', false) || can('editar-empleado-sueldos', false);
    $puedeEditarEntrega = can('editar-entrega-prenda', false) || can('editar-lineas-entrega-prenda', false);
    $puedeVerArticulo = can('editar-articulos', false) || can('listar-articulos', false) || can('actualizar-articulos', false);
    $puedeVerPrenda = can('editar-prenda-sueldos', false) || can('listar-prenda-sueldos', false);
    $puedeVerDeposito = can('editar-depositos', false) || can('listar-depositos', false);
    $puedeVerColor = can('editar-colores', false) || can('listar-colores', false);
    $puedeVerTalle = can('editar-talles', false) || can('listar-talles', false);
    $totalRegistros = method_exists($datas, 'total') ? $datas->total() : $datas->count();
    $fmtUnidades = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ',');
    $camposOrdenablesThead = EntregaPrendaListadoFiltros::camposOrdenables();
@endphp

@section('contenido')
<div class="row oc-ui">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between py-2">
                <h3 class="card-title mb-0"><i class="fa fa-tshirt"></i> Entregas de indumentaria</h3>
                <div class="card-tools ml-auto">
                    @include('includes.sueldos.boton-manual')
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif

            @include('sueldos.entrega_prenda.partials.filtros_externos')

            <form method="get" action="{{ route('entrega_prenda_reporte') }}" id="form-filtros-entrega-prenda" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if (($filtros['empresa_scope'] ?? '') === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif

                <div class="card-body py-2 border-bottom">
                    <div class="form-row align-items-end">
                        <div class="form-group col-auto mb-0 mr-2">
                            <label class="small mb-0">Año</label>
                            <input type="number" name="anio" class="form-control form-control-sm" style="width:5.5rem" min="2000" max="2100"
                                   value="{{ ! empty($filtros['anio']) ? $filtros['anio'] : '' }}" placeholder="Todos" title="Vacío = todos los años">
                        </div>
                        <div class="form-group col-auto mb-0 mr-2">
                            <label class="small mb-0">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-auto mb-0 mr-2">
                            <label class="small mb-0">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3 mb-0 mr-2">
                            <label class="small mb-0">Agrupamiento</label>
                            <select name="agrupamiento_id" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach ($agrupamientos as $agrupamiento)
                                    <option value="{{ $agrupamiento->id }}" {{ (int) ($filtros['agrupamiento_id'] ?? 0) === (int) $agrupamiento->id ? 'selected' : '' }}>{{ $agrupamiento->descripcion }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-auto mb-0">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                            @if ($mostrarLimpiar)
                                <a href="{{ $limpiarUrl }}" class="btn btn-outline-warning btn-sm" title="Limpiar año, fechas, agrupamiento y búsqueda">
                                    <i class="fa fa-eraser"></i> Limpiar
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('entrega_prenda_reporte') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info' }} {{ $qbeAbierto ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Legajo, empleado, prenda o SKU"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($mostrarLimpiar)
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning" title="Limpiar año, fechas, agrupamiento y búsqueda">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                        @if (($ordenActualThead ?? []) !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden de instalación y lo saca de la vista">
                                <i class="fa fa-undo"></i> Orden estándar
                            </a>
                        @endif

                    </div>
                </div>
                @include('sueldos.entrega_prenda.partials.workbench_qbe')
                @php
                    $ejesVisual = EntregaPrendaListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Sueldos\EntregaPrendaListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'entrega_prenda_reporte',
            ])
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="px-3 pt-1 pb-1">
                <div class="card card-outline card-info mb-0 lw-cortes">
                    <div class="card-header py-1 px-3 d-flex flex-wrap align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm lw-cortes-toggle lw-grafico-toggle collapsed" id="btn-entrega-grafico"
                                data-toggle="collapse" data-target="#entrega-grafico-body"
                                aria-expanded="false" aria-controls="entrega-grafico-body"
                                title="Mostrar entregas (líneas) por prenda">
                            <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                            <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                            Entregas por prenda
                        </button>
                        <span class="text-muted small">Universo del filtro, no solo la página.</span>
                    </div>
                    <div class="collapse" id="entrega-grafico-body">
                        <div class="card-body py-2">
                            @if (($graficoPorPrenda['total'] ?? 0) === 0)
                                <p class="text-muted mb-0">No hay entregas en este filtro para graficar.</p>
                            @else
                                <div style="height:260px;">
                                    <canvas id="entrega-grafico-prenda"></canvas>
                                </div>
                                @if (! empty($graficoPorPrenda['aviso']))
                                    <p class="small text-muted mb-0 mt-1">{{ $graficoPorPrenda['aviso'] }}</p>
                                @elseif (! empty($graficoPorPrenda['truncado']))
                                    <p class="small text-muted mb-0 mt-1">El gráfico muestra las prendas con más líneas del filtro; el resto se agrupa en «Otras».</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="tabla-ancha-grilla" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap card-body table-responsive p-0">
                    <table class="tabla-ancha table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('sueldos.entrega_prenda.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="text-nowrap text-left" style="width:90px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = $filtrosConsulta['agrupar'] ?? [];
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Sueldos\EntregaPrendaListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = max(1, count($columnasVisibles)) + 1;
                        @endphp
                        @forelse ($filasVista as $filaVista)
                            @if (($filaVista['type'] ?? '') === 'header')
                                <tr class="lw-group-header lw-group-nivel-{{ (int) ($filaVista['nivel'] ?? 0) }}">
                                    <td colspan="{{ $colspanGrilla }}">
                                        <i class="fa fa-folder-open-o"></i>
                                        <strong>{{ $filaVista['label'] }}:</strong>
                                        {{ $filaVista['valor'] !== '' ? $filaVista['valor'] : '(vacío)' }}
                                        <span class="lw-group-count">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php $data = $filaVista['row']; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('sueldos.entrega_prenda.partials.workbench_celda', [
                                            'key' => $keyColumna,
                                            'data' => $data,
                                            'puedeVerEmpleado' => $puedeVerEmpleado,
                                            'puedeVerArticulo' => $puedeVerArticulo,
                                            'puedeVerPrenda' => $puedeVerPrenda,
                                            'puedeVerDeposito' => $puedeVerDeposito,
                                            'puedeVerColor' => $puedeVerColor,
                                            'puedeVerTalle' => $puedeVerTalle,
                                            'puedeEditarEntrega' => $puedeEditarEntrega,
                                        ])
                                    @endforeach
                                    <td class="text-nowrap align-middle text-left">
                                        @if (! empty($data->entrega_id) && $puedeEditarEntrega)
                                            <a href="{{ route('editar_entrega_prenda', ['entrega' => $data->entrega_id, 'retorno' => request()->fullUrl()]) }}" class="btn-accion-tabla tooltipsC" title="Editar entrega">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (! empty($data->entrega_id))
                                            <a href="{{ route('comprobante_entrega_prenda', ['entrega' => $data->entrega_id]) }}" class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" title="Comprobante PDF">
                                                <i class="fa fa-file-pdf"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $colspanGrilla }}" class="text-center text-muted py-4">Sin entregas para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>
        </div>
    </div>
</div>
@if ($datas)
    {{ $datas->appends($filtrosQuery ?? [])->links() }}
@endif
{{-- Reordenar/ocultar columnas en thead; agregar = Diseñar vista. --}}
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_listado_entrega_prenda',
    'paraQueryStringDnd' => [\App\Support\Sueldos\EntregaPrendaListadoFiltros::class, 'paraQueryString'],
    'filtrosDnd' => $filtros ?? [],
])
@include('sueldos.empleado.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_entrega_prenda',
    'rutaGuardarVista' => 'guardar_vista_listado_entrega_prenda',
    'rutaEliminarVista' => 'eliminar_vista_listado_entrega_prenda',
    'rutaPreview' => 'preview_workbench_entrega_prenda',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_listado_entrega_prenda',
    'camposOrdenables' => EntregaPrendaListadoFiltros::camposOrdenables(),
])
@endsection
