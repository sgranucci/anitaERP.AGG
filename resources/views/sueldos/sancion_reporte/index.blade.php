@extends("theme.$theme.layout")
@section('titulo')
    Consultas de sanciones
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('scripts')
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $empleadoWorkbenchJs = public_path('assets/pages/scripts/sueldos/empleado/workbench.js');
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/workbench.js') }}?v={{ file_exists($empleadoWorkbenchJs) ? filemtime($empleadoWorkbenchJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/tipo_sancion/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/motivo_sancion/consulta.js') }}"></script>
@if (($graficoPorTipo['total'] ?? 0) > 0)
<script src="{{ asset('assets/lte/plugins/chart.js/Chart.min.js') }}"></script>
<script>
(function () {
    var datos = @json($graficoPorTipo ?? []);
    var grafico = null;
    function dibujar() {
        var canvas = document.getElementById('sancion-grafico-tipo');
        if (!canvas || typeof Chart === 'undefined' || !datos.labels || !datos.labels.length) {
            return;
        }
        if (!grafico) {
            grafico = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: datos.labels,
                    datasets: [{
                        label: 'Cantidad',
                        data: datos.cantidades,
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
                                var importe = datos.importes[i] || 0;
                                return cant + ' sanciones · ' + importe.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    },
                    scales: {
                        yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                        xAxes: [{ ticks: { autoSkip: false, maxRotation: 40, minRotation: 0 } }]
                    }
                }
            });
            return;
        }
        grafico.resize();
    }
    var panel = document.getElementById('sancion-grafico-body');
    if (panel && window.jQuery) {
        window.jQuery(panel).on('shown.bs.collapse', dibujar);
    }
})();
</script>
@endif
<script>
(function ($) {
    function datosFila($btn) {
        return $btn.closest('tr').data('sancion') || {};
    }
    function abrir(data, editar) {
        var $f = $('#form-sancion-reporte');
        var base = (typeof carpetaBase === 'string' ? carpetaBase : '');
        $f.attr('action', base + '/sueldos/sancion/' + (data.id || '0'));
        $f.find('[name=tipo_sancion_id]').val(data.tipo_sancion_id || '');
        $f.find('.codigotipo_sancion').val(data.tipo_codigo || '');
        $f.find('.nombretipo_sancion').val(data.tipo_nombre || '');
        $f.find('[name=motivo_sancion_id]').val(data.motivo_sancion_id || '');
        $f.find('.codigomotivo_sancion').val(data.motivo_codigo || '');
        $f.find('.nombremotivo_sancion').val(data.motivo_nombre || '');
        ['fecha_hecho', 'fecha_desde', 'fecha_hasta', 'cant_dias', 'fecha_notificacion', 'fecha_recepcion', 'importe_perdida', 'comentario', 'descargo_texto', 'resolucion_texto'].forEach(function (nombre) {
            $f.find('[name="' + nombre + '"]').val(data[nombre] == null ? '' : data[nombre]);
        });
        $('#sancion-reporte-estado').text(data.estado_label ? ('Estado: ' + data.estado_label + (editar ? '' : '. Solo consulta.')) : '');
        $('#sancion-reporte-error').addClass('d-none').text('');
        $f.find('input, select, textarea, button.consultatipo_sancion, button.consultamotivo_sancion').prop('disabled', !editar);
        $f.find('[name=_token], [name=_method], [name=desde_reporte]').prop('disabled', false);
        $('#btn-guardar-sancion-reporte').toggle(!!editar);
        $('#modal-sancion-reporte .modal-title').text(editar ? 'Modificar sanción' : 'Sanción');
        $('#modal-sancion-reporte').modal('show');
    }
    $(document).on('click', '.btn-reporte-ver-sancion', function () {
        abrir(datosFila($(this)), false);
    });
    $(document).on('click', '.btn-reporte-editar-sancion', function () {
        abrir(datosFila($(this)), true);
    });
    $('#form-sancion-reporte').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this);
        $('#sancion-reporte-error').addClass('d-none').text('');
        $.ajax({
            url: $f.attr('action'),
            type: 'POST',
            data: $f.serialize()
        }).done(function (resp) {
            if (resp && resp.error) {
                $('#sancion-reporte-error').removeClass('d-none').text(resp.texto || resp.mensaje || 'No se pudo guardar.');
                return;
            }
            window.location.reload();
        }).fail(function (xhr) {
            var msg = 'No se pudo guardar.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).map(function (v) {
                    return Array.isArray(v) ? v.join(' ') : v;
                }).join(' ');
            } else if (xhr.responseJSON && (xhr.responseJSON.texto || xhr.responseJSON.mensaje)) {
                msg = xhr.responseJSON.texto || xhr.responseJSON.mensaje;
            }
            $('#sancion-reporte-error').removeClass('d-none').text(msg);
        });
    });
})(jQuery);
</script>
@endsection

@php
    use App\Support\Sueldos\SancionReporteListadoFiltros;
    $columnasVisibles = $columnasVisibles ?? [];
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $limpiarQ = ['filtro_limpiar' => 1];
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarUrl = route('sancion_reporte_sueldos', $limpiarQ);
    $hayTexto = trim((string) ($filtros['apellido'] ?? '')) !== ''
        || trim((string) ($filtros['valor'] ?? '')) !== ''
        || $qbeAbierto
        || ($filtros['estado'] ?? '') !== ''
        || ($filtros['legajo_desde'] ?? '') !== ''
        || ($filtros['legajo_hasta'] ?? '') !== ''
        || ! empty($filtros['tipo_sancion_id'])
        || ! empty($filtros['motivo_sancion_id']);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Consultas de sanciones</h3>
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
            <form method="get" action="{{ route('sancion_reporte_sueldos') }}" id="form-filtros-sancion-reporte" class="mb-0">
                <input type="hidden" name="consultar" value="1">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="card-body pb-2 border-bottom">
                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'reporte_clave' => 'sancion-reporte',
                        'id_prefix' => 'sancion',
                        'col_label' => 'col-lg-2',
                        'col_body' => 'col-lg-10',
                    ])
                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Desde</label>
                            <input type="date" name="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Apellido / nombre</label>
                            <input type="search" name="apellido" class="form-control form-control-sm" value="{{ $filtros['apellido'] ?? '' }}" placeholder="Apellido o nombre" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2">
                            <label class="small mb-0">Legajo desde / hasta</label>
                            <div class="d-flex">
                                <input type="number" name="legajo_desde" class="form-control form-control-sm mr-1" value="{{ $filtros['legajo_desde'] ?? '' }}" placeholder="Desde">
                                <input type="number" name="legajo_hasta" class="form-control form-control-sm" value="{{ $filtros['legajo_hasta'] ?? '' }}" placeholder="Hasta">
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small mb-0">Estado</label>
                            <select name="estado" class="form-control form-control-sm">
                                <option value="">Todos</option>
                                @foreach ($estados as $cod => $label)
                                    <option value="{{ $cod }}" {{ ($filtros['estado'] ?? '') === $cod ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row align-items-end">
                        <div class="col-md-4 mb-2">
                            @include('sueldos.partials.campo_consulta_tipo_sancion', [
                                'inputName' => 'tipo_sancion_id',
                                'inputId' => 'filtro_tipo_sancion_id',
                                'tipoId' => $filtros['tipo_sancion_id'] ?? '',
                                'codigo' => $tipoSel->codigo ?? '',
                                'descripcion' => $tipoSel->nombre ?? '',
                                'label' => 'Tipo de sanción',
                                'required' => false,
                            ])
                        </div>
                        <div class="col-md-4 mb-2">
                            @include('sueldos.partials.campo_consulta_motivo_sancion', [
                                'inputName' => 'motivo_sancion_id',
                                'inputId' => 'filtro_motivo_sancion_id',
                                'motivoId' => $filtros['motivo_sancion_id'] ?? '',
                                'codigo' => $motivoSel->codigo ?? '',
                                'descripcion' => $motivoSel->nombre ?? '',
                                'label' => 'Motivo',
                                'required' => false,
                            ])
                        </div>
                        <div class="form-group col-md-2 mb-2">
                            <div class="form-check mt-3">
                                <input type="checkbox" name="incluir_comentario" value="1" class="form-check-input" id="incluir_comentario"
                                       {{ !empty($filtros['incluir_comentario']) ? 'checked' : '' }}>
                                <label class="form-check-label small" for="incluir_comentario">Solo con comentario</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2 mb-2 text-right">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Consultar</button>
                        </div>
                    </div>
                </div>
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('sancion_reporte_sueldos') }}"
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
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="Legajo, apellido, tipo o motivo"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if ($hayTexto)
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('sueldos.sancion_reporte.partials.workbench_qbe')
                @php
                    $ejesVisual = SancionReporteListadoFiltros::camposOrdenables();
                    $medidasVisual = \App\Support\Listado\ListadoVisualSupport::medidasDisponibles($ejesVisual);
                    $lienzoVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Sueldos\SancionReporteListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'sancion_reporte_sueldos',
            ])
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="px-3 pt-2 pb-1">
                <div class="card card-outline card-info mb-0 lw-cortes">
                    <div class="card-header py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                        <button type="button" class="btn btn-sm lw-cortes-toggle lw-grafico-toggle collapsed" id="btn-sancion-grafico"
                                data-toggle="collapse" data-target="#sancion-grafico-body"
                                aria-expanded="false" aria-controls="sancion-grafico-body"
                                title="Mostrar sanciones por tipo">
                            <i class="fa fa-chevron-down lw-cortes-ico lw-cortes-ico-abierto" aria-hidden="true"></i>
                            <i class="fa fa-chevron-right lw-cortes-ico lw-cortes-ico-cerrado" aria-hidden="true"></i>
                            Sanciones por tipo
                        </button>
                        <span class="text-muted small">Universo del filtro, no solo la página.</span>
                    </div>
                    <div class="collapse" id="sancion-grafico-body">
                        <div class="card-body py-2">
                            @if (($graficoPorTipo['total'] ?? 0) === 0)
                                <p class="text-muted mb-0">No hay sanciones en este filtro para graficar.</p>
                            @else
                                <div style="height:220px;">
                                    <canvas id="sancion-grafico-tipo"></canvas>
                                </div>
                                @if (! empty($graficoPorTipo['truncado']))
                                    <p class="small text-muted mb-0 mt-1">El gráfico muestra los grupos más grandes del filtro.</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_sancion_reporte_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                <th>{{ ($etiquetasColumnas ?? [])[$keyColumna] ?? $keyColumna }}</th>
                            @endforeach
                            <th class="text-nowrap" style="width:110px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = $filtrosConsulta['agrupar'] ?? [];
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Sueldos\SancionReporteListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = max(1, count($columnasVisibles)) + 1;
                            $puedeModificarSancion = can('actualizar-sancion-empleado-sueldos', false);
                            $puedeBorrarSancion = can('anular-sancion-empleado-sueldos', false);
                            $puedeImprimirSancion = can('imprimir-sancion-sueldos', false);
                            $retornoReporte = url()->full();
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
                                @php
                                    $data = $filaVista['row'];
                                    $editableFila = $puedeModificarSancion && $data->esEditable();
                                    $payloadSancion = [
                                        'id' => $data->id,
                                        'tipo_sancion_id' => $data->tipo_sancion_id,
                                        'tipo_codigo' => optional($data->tipo)->codigo,
                                        'tipo_nombre' => optional($data->tipo)->nombre,
                                        'motivo_sancion_id' => $data->motivo_sancion_id,
                                        'motivo_codigo' => optional($data->motivo)->codigo,
                                        'motivo_nombre' => optional($data->motivo)->nombre,
                                        'fecha_hecho' => optional($data->fecha_hecho)->format('Y-m-d'),
                                        'fecha_desde' => optional($data->fecha_desde)->format('Y-m-d'),
                                        'fecha_hasta' => optional($data->fecha_hasta)->format('Y-m-d'),
                                        'cant_dias' => $data->cant_dias,
                                        'importe_perdida' => $data->importe_perdida,
                                        'fecha_notificacion' => optional($data->fecha_notificacion)->format('Y-m-d'),
                                        'fecha_recepcion' => optional($data->fecha_recepcion)->format('Y-m-d'),
                                        'comentario' => $data->comentario,
                                        'descargo_texto' => $data->descargo_texto,
                                        'resolucion_texto' => $data->resolucion_texto,
                                        'estado_label' => \App\Support\Sueldos\EmpleadoSancionSupport::etiquetaEstado($data->estado),
                                        'editable' => $editableFila,
                                    ];
                                @endphp
                                <tr class="{{ ($data->estado ?? '') === 'anulada' ? 'table-secondary' : (($data->estado ?? '') === 'borrador' ? 'table-warning' : '') }}"
                                    data-sancion='@json($payloadSancion)'>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('sueldos.sancion_reporte.partials.workbench_celda', ['key' => $keyColumna, 'data' => $data])
                                    @endforeach
                                    <td class="text-nowrap align-middle text-right">
                                        <button type="button" class="btn-accion-tabla tooltipsC btn-reporte-ver-sancion" title="Ver sanción">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        @if ($editableFila)
                                            <button type="button" class="btn-accion-tabla tooltipsC btn-reporte-editar-sancion" title="Modificar sanción">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                        @endif
                                        @if ($puedeImprimirSancion)
                                            <a href="{{ route('notificacion_sancion_sueldos', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" title="Carta PDF">
                                                <i class="fa fa-file-pdf"></i>
                                            </a>
                                        @endif
                                        @if ($puedeBorrarSancion)
                                            <form action="{{ route('eliminar_sancion_empleado_sueldos', ['id' => $data->id]) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar esta sanción?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="desde_reporte" value="1">
                                                <input type="hidden" name="retorno" value="{{ $retornoReporte }}">
                                                <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ $colspanGrilla }}" class="text-center text-muted py-4">Sin sanciones para el filtro.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@if ($datas)
    {{ $datas->appends($filtrosQuery ?? [])->links() }}
@endif
@include('sueldos.empleado.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_sancion_reporte_sueldos',
    'rutaGuardarVista' => 'guardar_vista_listado_sancion_reporte_sueldos',
    'rutaEliminarVista' => 'eliminar_vista_listado_sancion_reporte_sueldos',
    'rutaPreview' => 'preview_workbench_sancion_reporte_sueldos',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_listado_sancion_reporte_sueldos',
    'camposOrdenables' => SancionReporteListadoFiltros::camposOrdenables(),
])
@include('includes.sueldos.modalconsultatipo_sancion')
@include('includes.sueldos.modalconsultamotivo_sancion')
<div class="modal fade" id="modal-sancion-reporte" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" id="form-sancion-reporte" class="modal-content" action="{{ url('/sueldos/sancion/0') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="desde_reporte" value="1">
            <div class="modal-header">
                <h5 class="modal-title">Sanción</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="sancion-reporte-estado"></p>
                <div id="sancion-reporte-error" class="alert alert-danger d-none"></div>
                <div class="form-row">
                    <div class="col-md-6 mb-2">
                        @include('sueldos.partials.campo_consulta_tipo_sancion', [
                            'inputName' => 'tipo_sancion_id',
                            'inputId' => 'reporte_sancion_tipo_id',
                            'tipoId' => '',
                            'label' => 'Tipo de sanción',
                            'required' => true,
                        ])
                    </div>
                    <div class="col-md-6 mb-2">
                        @include('sueldos.partials.campo_consulta_motivo_sancion', [
                            'inputName' => 'motivo_sancion_id',
                            'inputId' => 'reporte_sancion_motivo_id',
                            'motivoId' => '',
                            'label' => 'Motivo',
                            'required' => true,
                        ])
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Fecha del hecho</label>
                        <input type="date" name="fecha_hecho" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Desde</label>
                        <input type="date" name="fecha_desde" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Hasta</label>
                        <input type="date" name="fecha_hasta" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Días</label>
                        <input type="number" name="cant_dias" class="form-control form-control-sm" min="0" max="999">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Notificación</label>
                        <input type="date" name="fecha_notificacion" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0">Recepción</label>
                        <input type="date" name="fecha_recepcion" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label class="small mb-0" title="Salario que no se paga por la sanción. No es una multa.">Importe no cobrado</label>
                        <input type="number" step="0.01" min="0" name="importe_perdida" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-12 mb-2">
                        <label class="small mb-0">Comentario / causa</label>
                        <textarea name="comentario" class="form-control form-control-sm" rows="2" required maxlength="4000"></textarea>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-0">Descargo</label>
                        <textarea name="descargo_texto" class="form-control form-control-sm" rows="2" maxlength="4000"></textarea>
                    </div>
                    <div class="form-group col-md-6 mb-2">
                        <label class="small mb-0">Resolución</label>
                        <textarea name="resolucion_texto" class="form-control form-control-sm" rows="2" maxlength="4000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                <button type="submit" class="btn btn-primary" id="btn-guardar-sancion-reporte"><i class="fa fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
@endsection
