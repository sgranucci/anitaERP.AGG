@extends("theme.$theme.layout")

@section('titulo')
    Cajeros
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
@php
    use App\Support\Caja\CajeroCrownListadoColumnas;
    use App\Support\Caja\CajeroCrownListadoFiltros;
    $estadoActual = $filtros['filtro_estado'] ?? CajeroCrownListadoFiltros::FILTRO_ACTIVOS;
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info lw-workbench shadow-sm" data-form="#form-filtros-cajero-crown">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0"><i class="fa fa-id-badge mr-1"></i> Cajeros</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if ($sala && can('crear-tesoreria-crown-cajero', false))
                        <a href="{{ route('crear_tesoreria_crown_cajero', ['sala_id' => $sala->id]) }}" class="btn btn-light btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo cajero
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('tesoreria_crown_cajero') }}" id="form-filtros-cajero-crown" class="mb-0">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="filtro_estado" id="filtro_estado" value="{{ $estadoActual }}">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', [
                    'rutaListado' => 'tesoreria_crown_cajero',
                    'limpiarParams' => ($sala ?? null) ? ['sala_id' => $sala->id, 'vista_estandar' => 1] : ['vista_estandar' => 1],
                ])
                <div class="px-3 py-2 border-bottom bg-white d-flex flex-wrap align-items-center" style="gap:.35rem;">
                    <span class="text-muted small mr-1">Estado:</span>
                    @foreach ([
                        CajeroCrownListadoFiltros::FILTRO_ACTIVOS => 'Activos',
                        CajeroCrownListadoFiltros::FILTRO_INACTIVOS => 'Inactivos',
                        CajeroCrownListadoFiltros::FILTRO_TODOS => 'Todos',
                    ] as $valorEstado => $etiquetaEstado)
                        <button type="submit" class="btn btn-sm {{ $estadoActual === $valorEstado ? 'btn-info' : 'btn-outline-info' }}"
                                onclick="document.getElementById('filtro_estado').value='{{ $valorEstado }}';">
                            {{ $etiquetaEstado }}
                        </button>
                    @endforeach
                </div>
                @include('caja.tesoreria_crown.cajero.partials.workbench_qbe')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_cajero',
                'queryExtra' => array_filter([
                    'filtro_estado' => $estadoActual !== CajeroCrownListadoFiltros::FILTRO_ACTIVOS ? $estadoActual : null,
                ]),
                'exportRuta' => $sala ? 'listar_tesoreria_crown_cajero' : null,
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            @include('includes.listado.workbench_cortes')
            <div class="card-body table-responsive p-0">
                <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('caja.tesoreria_crown.partials.workbench_th', [
                                    'key' => $keyColumna,
                                    'rutaListado' => 'tesoreria_crown_cajero',
                                ])
                            @endforeach
                            <th class="text-left" style="width:90px;" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    <td>
                                        @if ($keyColumna === 'codigo')
                                            <a class="text-primary font-weight-bold" href="{{ route('editar_tesoreria_crown_cajero', $fila->id) }}">{{ $fila->codigo }}</a>
                                        @elseif ($keyColumna === 'activo')
                                            @if ($fila->activo)
                                                <span class="badge badge-success">Activo</span>
                                            @else
                                                <span class="badge badge-secondary">Inactivo</span>
                                            @endif
                                        @elseif ($keyColumna === 'externos')
                                            @forelse ($fila->externos as $ext)
                                                <span class="badge badge-info mr-1">{{ $ext->sistema }}</span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        @else
                                            {{ CajeroCrownListadoColumnas::valorCelda($fila, $keyColumna) }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-left text-nowrap">
                                    @if (can('editar-tesoreria-crown-cajero', false))
                                        <a href="{{ route('editar_tesoreria_crown_cajero', $fila->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-cajero', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_cajero', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el cajero {{ $fila->codigo }}?');">
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
                                <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">
                                    {{ $tieneCriterios ? 'No hay cajeros para este filtro.' : 'No hay cajeros en esta sala.' }}
                                </td>
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
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_cajero',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_cajero',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_cajero',
    'rutaPreview' => 'preview_tesoreria_crown_cajero',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_cajero',
])
@endsection
