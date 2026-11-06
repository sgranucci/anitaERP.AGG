@extends("theme.$theme.layout")

@section('titulo')
    Resultado online
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
    <script>
    (function () {
        var datos = @json($grafico ?? []);
        var grafico = null;
        function dibujar() {
            var canvas = document.getElementById('resultado-online-grafico');
            if (!canvas || typeof Chart === 'undefined' || !datos.labels || !datos.labels.length) {
                return;
            }
            if (!grafico) {
                grafico = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: datos.labels,
                        datasets: [{
                            label: 'Resultado',
                            data: datos.montos,
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
                                    var monto = datos.montos[item.index] || 0;
                                    var cant = datos.cantidades[item.index] || 0;
                                    return monto.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                        + ' · ' + cant + ' días';
                                }
                            }
                        },
                        scales: {
                            yAxes: [{ ticks: { beginAtZero: true } }],
                            xAxes: [{ ticks: { autoSkip: false, maxRotation: 40, minRotation: 0 } }]
                        }
                    }
                });
                return;
            }
            grafico.resize();
        }
        var panel = document.getElementById('resultado-online-grafico-body');
        if (panel && window.jQuery) {
            window.jQuery(panel).on('shown.bs.collapse', dibujar);
        }
    })();
    </script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info lw-workbench shadow-sm" data-form="#form-filtros-resultado-online">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Resultado online</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if ($sala && can('crear-tesoreria-crown-resultado-online', false))
                        <a href="{{ route('crear_tesoreria_crown_resultado_online', ['sala_id' => $sala->id]) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo resultado
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_resultado_online') }}" id="form-filtros-resultado-online" class="mb-0">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', [
                    'rutaListado' => 'tesoreria_crown_resultado_online',
                    'limpiarParams' => ($sala ?? null) ? ['sala_id' => $sala->id, 'vista_estandar' => 1] : ['vista_estandar' => 1],
                ])
                <div class="px-3 py-2 border-bottom bg-light d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    <label for="fecha_desde" class="mb-0 small text-muted">Desde</label>
                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" style="width:10rem;" value="{{ $filtros['fecha_desde'] ?? '' }}">
                    <label for="fecha_hasta" class="mb-0 small text-muted">Hasta</label>
                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" style="width:10rem;" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Aplicar</button>
                </div>
                @include('caja.tesoreria_crown.resultado_online.partials.workbench_qbe')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_resultado_online',
                'exportRuta' => 'listar_tesoreria_crown_resultado_online',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('includes.listado.workbench_cortes')
            <div class="px-3 pt-2">
                <div class="card card-outline card-info mb-2">
                    <div class="card-header py-2 px-3">
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" id="btn-resultado-online-grafico"
                                data-toggle="collapse" data-target="#resultado-online-grafico-body"
                                aria-expanded="false" aria-controls="resultado-online-grafico-body"
                                title="Mostrar resultado por mes">
                            <i class="fa fa-bar-chart"></i> Resultado por mes
                        </button>
                        <span class="text-muted small">Universo del filtro, no solo la página.</span>
                    </div>
                    <div class="collapse" id="resultado-online-grafico-body">
                        <div class="card-body py-2">
                            @if (($grafico['total'] ?? 0) === 0)
                                <p class="text-muted mb-0">No hay días en este filtro para graficar.</p>
                            @else
                                <div style="height:220px;">
                                    <canvas id="resultado-online-grafico"></canvas>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'tesoreria_crown_resultado_online'])
                            @endforeach
                            <th class="text-left" style="width:90px;" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td class="{{ \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::esNumero($keyColumna) ? 'text-right' : '' }}">
                                        @if ($keyColumna === 'fecha')
                                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_resultado_online', ['id' => $fila->id, 'sala_id' => $fila->sala_id]) }}">{{ $fila->fecha?->format('d/m/Y') }}</a>
                                        @else
                                            {{ \App\Support\Caja\TesoreriaCrownResultadoOnlineListadoColumnas::valorCelda($fila, $keyColumna) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-left text-nowrap">
                                    @if (can('editar-tesoreria-crown-resultado-online', false))
                                        <a href="{{ route('editar_tesoreria_crown_resultado_online', ['id' => $fila->id, 'sala_id' => $fila->sala_id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-resultado-online', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_resultado_online', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el resultado del {{ $fila->fecha?->format('d/m/Y') }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay resultados para este filtro.' : 'No hay resultados cargados en esta sala.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($filas->total() > 0)
                    <span class="text-muted">{{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</span>
                @endif
                {{ $filas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_resultado_online',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_resultado_online',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_resultado_online',
    'rutaPreview' => 'preview_tesoreria_crown_resultado_online',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_resultado_online',
])
@endsection
