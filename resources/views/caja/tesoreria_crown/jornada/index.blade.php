@extends("theme.$theme.layout")

@section('titulo')
    Jornadas de tesorería
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
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info lw-workbench shadow-sm" data-form="#form-filtros-tesoreria-crown-jornada">
            <div class="card-header lw-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Jornadas</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center" style="gap:.4rem;">
                    @if (! $abierta && $sala && can('crear-tesoreria-crown-jornada', false))
                        <button type="button" class="btn btn-light btn-sm" data-toggle="collapse" data-target="#panel-abrir-jornada" aria-expanded="false">
                            <i class="fa fa-plus-circle"></i> Abrir jornada
                        </button>
                    @endif
                </div>
            </div>
            @if (! $abierta && $sala && can('crear-tesoreria-crown-jornada', false))
                <div class="collapse border-bottom" id="panel-abrir-jornada">
                    <form method="POST" action="{{ route('abrir_tesoreria_crown_jornada') }}" class="form-inline px-3 py-2 mb-0 bg-light">
                        @csrf
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        <label for="fecha" class="mr-2 mb-0">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control form-control-sm mr-2" required value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        <button type="submit" class="btn btn-success btn-sm">Abrir</button>
                    </form>
                </div>
            @endif
            <form method="get" action="{{ route('tesoreria_crown_jornada') }}" id="form-filtros-tesoreria-crown-jornada" class="mb-0">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                @include('caja.tesoreria_crown.partials.workbench_barra', ['rutaListado' => 'tesoreria_crown_jornada'])
                @include('caja.tesoreria_crown.partials.workbench_qbe')
            </form>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_jornada',
                'exportRuta' => 'listar_tesoreria_crown_jornada',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'tesoreria_crown_jornada'])
                                @endforeach
                                <th class="text-center" style="width:140px;" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jornadas as $jornada)
                                @php $puedeBorrar = (int) $jornada->comprobantes_count === 0; @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        <td>
                                            @switch($keyColumna)
                                                @case('fecha')
                                                    <a class="text-primary" href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}">{{ $jornada->fecha->format('d/m/Y') }}</a>
                                                    @break
                                                @case('estado')
                                                    @if ($jornada->estaAbierta())
                                                        <span class="badge badge-success">Abierta</span>
                                                    @else
                                                        <span class="badge badge-secondary">Cerrada</span>
                                                    @endif
                                                    @break
                                                @case('asignaciones')
                                                    {{ $jornada->asignaciones_count }}
                                                    @break
                                                @case('movimientos')
                                                    {{ $jornada->comprobantes_count }}
                                                    @break
                                                @case('cajeros')
                                                    {{ $jornada->asignaciones->pluck('cajero.nombre')->filter()->unique()->implode(', ') ?: '—' }}
                                                    @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                    <td class="text-center text-nowrap">
                                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn-accion-tabla" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                        @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-jornada', false))
                                            <a href="{{ route('ver_tesoreria_crown_jornada', ['id' => $jornada->id, 'abrir' => 1]) }}" class="btn-accion-tabla" title="Abrir caja">
                                                <i class="fa fa-plus"></i>
                                            </a>
                                        @endif
                                        @if ($jornada->estaAbierta() && can('editar-tesoreria-crown-jornada', false))
                                            <form action="{{ route('cerrar_tesoreria_crown_jornada', $jornada->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Cerrar la jornada del {{ $jornada->fecha->format('d/m/Y') }}?');">
                                                @csrf
                                                <button type="submit" class="btn-accion-tabla" title="Cerrar">
                                                    <i class="fa fa-lock"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($puedeBorrar && can('editar-tesoreria-crown-jornada', false))
                                            <form action="{{ route('eliminar_tesoreria_crown_jornada', $jornada->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar la jornada del {{ $jornada->fecha->format('d/m/Y') }}? Se quitan las asignaciones de cajero.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-accion-tabla" title="Eliminar">
                                                    <i class="fa fa-times-circle text-danger"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columnasVisibles) + 1 }}" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay jornadas para este filtro.' : 'No hay jornadas en esta sala.' }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>
            <div class="card-footer clearfix">
                @if ($jornadas->total() > 0)
                    <span class="text-muted">{{ $jornadas->firstItem() }}–{{ $jornadas->lastItem() }} de {{ $jornadas->total() }}</span>
                @endif
                {{ $jornadas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.tesoreria_crown.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_jornada',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_jornada',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_jornada',
    'rutaPreview' => 'preview_tesoreria_crown_jornada',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_jornada',
])
@endsection
