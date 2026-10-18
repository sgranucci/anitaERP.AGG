@extends("theme.$theme.layout")
@section('titulo')
    Bienes de uso
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $bienUsoWorkbenchJs = public_path('assets/pages/scripts/contable/bien_uso/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/bien_uso/workbench.js') }}?v={{ file_exists($bienUsoWorkbenchJs) ? filemtime($bienUsoWorkbenchJs) : time() }}"></script>
@endsection

@php
    use App\Support\Contable\BienUsoListadoFiltros;

    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $columnasVisibles = $columnasVisibles ?? [];
    $estadoActual = $filtros['estado'] ?? 'A';
    $limpiarQ = [];
    if ($estadoActual === '') {
        $limpiarQ['filtro_estado'] = 'TODOS';
    } elseif ($estadoActual !== 'A') {
        $limpiarQ['filtro_estado'] = $estadoActual;
    }
    if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
        $limpiarQ['empresa_todas'] = 1;
    } elseif (! empty($filtros['empresa_id'])) {
        $limpiarQ['empresa_id'] = (int) $filtros['empresa_id'];
    }
    if (! empty($filtros['centrocosto_id'])) {
        $limpiarQ['filtro_centrocosto_id'] = (int) $filtros['centrocosto_id'];
    }
    if ($vistaActiva ?? null) {
        $limpiarQ['vista_id'] = $vistaActiva->id;
    }
    $limpiarQ['filtro_limpiar'] = 1;
    $limpiarUrl = route('bien_uso', $limpiarQ);
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Bienes de uso</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end" style="gap:.4rem;">
                    @if (can('crear-bien-uso', false))
                        <a href="{{ route('crear_bien_uso', $retornoListadoQuery) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus"></i> Nuevo bien de uso
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
            <form method="get" action="{{ route('bien_uso') }}" id="form-filtros-bien-uso" class="mb-0">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_modo" id="filtro_modo" value="{{ $filtros['modo'] ?? 'todos' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($estadoActual === '')
                    <input type="hidden" name="filtro_estado" value="TODOS">
                @elseif ($estadoActual !== 'A')
                    <input type="hidden" name="filtro_estado" value="{{ $estadoActual }}">
                @endif
                @if (($filtros['empresa_scope'] ?? '') === 'todas')
                    <input type="hidden" name="empresa_todas" value="1">
                @elseif (! empty($filtros['empresa_id']))
                    <input type="hidden" name="empresa_id" value="{{ $filtros['empresa_id'] }}">
                @endif
                @if (! empty($filtros['centrocosto_id']))
                    <input type="hidden" name="filtro_centrocosto_id" value="{{ $filtros['centrocosto_id'] }}">
                @endif
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('bien_uso') }}"
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
                               placeholder="UID, hostname, modelo, serie…"
                               autocomplete="off">
                        <button type="button" id="btn-lw-buscar-rapida" class="btn btn-sm btn-primary">
                            <i class="fa fa-search"></i>
                        </button>
                        @if (BienUsoListadoFiltros::tieneCriteriosTexto($filtros ?? []))
                            <a href="{{ $limpiarUrl }}" class="btn btn-sm btn-outline-warning">
                                <i class="fa fa-eraser"></i> Limpiar
                            </a>
                        @endif
                    </div>
                </div>
                @include('contable.bien_uso.partials.filtros_externos')
                @include('contable.bien_uso.partials.workbench_qbe')
            </form>
            @if (! empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Mostrando bienes del pool: <strong>{{ $alcance_centro_costo }}</strong>
                </div>
            @endif
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
            <div class="card-body py-2 border-bottom bg-white">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_bien_uso',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('contable.bien_uso.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $agruparActivo = \App\Support\Listado\ListadoAgrupacionSupport::normalizar(
                                $filtros['agrupar'] ?? [],
                                BienUsoListadoFiltros::camposOrdenables()
                            );
                            $filasVista = \App\Support\Listado\ListadoAgrupacionSupport::segmentar(
                                $datas,
                                $agruparActivo,
                                static fn ($row, string $campo): string => \App\Support\Contable\BienUsoListadoColumnas::valorCelda($row, $campo),
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
                                        <span class="lw-group-count" title="{{ ! empty($filaVista['count_universo']) ? 'Universo filtrado' : 'Página visible' }}">
                                            {{ number_format((int) ($filaVista['count'] ?? 0), 0, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @else
                                @php $data = $filaVista['row']; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('contable.bien_uso.partials.workbench_celda', ['key' => $keyColumna, 'data' => $data])
                                    @endforeach
                                    <td class="text-nowrap align-middle">
                                        @if (can('editar-bien-uso', false))
                                            <a href="{{ route('editar_bien_uso', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        @if (can('borrar-bien-uso', false))
                                            <form action="{{ route('eliminar_bien_uso', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-4">No hay bienes de uso con estos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@include('contable.bien_uso.partials.workbench_modales')
@endsection
