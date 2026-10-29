@extends("theme.$theme.layout")
@section('titulo')
    Movimientos de Ordenes de Trabajo
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $movOtJs = public_path('assets/pages/scripts/produccion/movimientoordentrabajo/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/produccion/movimientoordentrabajo/workbench.js') }}?v={{ file_exists($movOtJs) ? filemtime($movOtJs) : time() }}"></script>
@endsection

@php
    use App\Support\Produccion\MovimientoOrdentrabajoListadoFiltros;

    $otId = (int) ($otId ?? 0);
    $estadoActual = $filtros['estado_tarea'] ?? MovimientoOrdentrabajoListadoFiltros::ESTADO_EN_CURSO;
    $limpiarQ = ['filtro_estado_tarea' => $estadoActual, 'filtro_limpiar' => 1];
    if ($otId > 0) {
        $limpiarQ['ordentrabajo_id'] = $otId;
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $accionListado = $otId > 0
        ? route('consultamovimientoordentrabajo', ['id' => $otId])
        : route('movimientoordentrabajo');
    $limpiarUrl = $otId > 0
        ? route('consultamovimientoordentrabajo', ['id' => $otId] + $limpiarQ)
        : route('movimientoordentrabajo', $limpiarQ);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'movot-export-overlay',
            'tituloId' => 'movot-export-titulo',
            'subtituloId' => 'movot-export-subtitulo',
            'titulo' => 'Exportando…',
            'subtitulo' => 'Generando el archivo. No cierre la página.',
        ])
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Movimientos de OT</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-movimientos-orden-trabajo', false))
                        <a href="{{ route('crear_movimientoordentrabajo') }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo registro
                        </a>
                    @endif
                </div>
            </div>
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ $accionListado }}" id="form-filtros-movimiento-ot" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_estado_tarea" value="{{ $estadoActual }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @if ($otId > 0)
                    <input type="hidden" name="ordentrabajo_id" value="{{ $otId }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ $accionListado }}"
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
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                            <i class="fa fa-filter"></i> QBE
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        <input type="search" id="lw-search-rapida" class="form-control form-control-sm lw-search-rapida"
                               value="{{ ($filtros['modo'] ?? '') !== 'qbe' ? ($filtros['valor'] ?? '') : '' }}"
                               placeholder="OT, tarea, operación, empleado…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (MovimientoOrdentrabajoListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('produccion.movimientoordentrabajo.partials.filtros_externos')
                @include('produccion.movimientoordentrabajo.partials.workbench_qbe')
            </form>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_movimientoordentrabajo',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('produccion.movimientoordentrabajo.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="text-nowrap" style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                MovimientoOrdentrabajoListadoFiltros::camposOrdenables()
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Produccion\MovimientoOrdentrabajoListadoColumnas::valorCelda($row, $campo),
                                $etiquetasColumnas ?? [],
                                ! empty($cortes['por_clave']) ? $cortes['por_clave'] : null
                            );
                            $colspanGrilla = count($columnasVisibles) + 1;
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
                                        @include('produccion.movimientoordentrabajo.partials.workbench_celda', ['key' => $keyColumna, 'data' => $data])
                                    @endforeach
                                    <td class="text-nowrap align-middle">
                                        @if (can('editar-movimientos-orden-trabajo', false))
                                            <a href="{{ route('editar_movimientoordentrabajo', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('borrar-movimientos-orden-trabajo', false))
                                            <form action="{{ route('eliminar_movimientoordentrabajo', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                                @csrf @method("delete")
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
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">No hay movimientos con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@include('produccion.movimientoordentrabajo.partials.workbench_modales')
@endsection
