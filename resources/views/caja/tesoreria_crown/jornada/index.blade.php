@extends("theme.$theme.layout")

@section('titulo')
    Jornadas de tesorería
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<style>
    /* Jornadas: caber en una pantalla; nombres largos con ellipsis + title. */
    .lw-workbench #tabla-paginada th[data-col-key="fecha"],
    .lw-workbench #tabla-paginada td[data-col-key="fecha"] { width: 6.5rem; }
    .lw-workbench #tabla-paginada th[data-col-key="estado"],
    .lw-workbench #tabla-paginada td[data-col-key="estado"] { width: 5.5rem; }
    .lw-workbench #tabla-paginada th[data-col-key="asignaciones"],
    .lw-workbench #tabla-paginada td[data-col-key="asignaciones"],
    .lw-workbench #tabla-paginada th[data-col-key="movimientos"],
    .lw-workbench #tabla-paginada td[data-col-key="movimientos"] { width: 6.5rem; }
    .lw-workbench #tabla-paginada th.lw-col-acciones,
    .lw-workbench #tabla-paginada td.lw-col-acciones {
        width: 10.5rem;
        min-width: 10.5rem;
        max-width: 10.5rem;
    }
</style>
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}" type="text/javascript"></script>
    @php $tablaAnchaJs = public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js'); @endphp<script src="{{ asset('assets/pages/scripts/listado/workbench-columnas-dnd.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-columnas-dnd.js')) }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ file_exists($tablaAnchaJs) ? filemtime($tablaAnchaJs) : time() }}"></script>
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
                    <form method="POST" action="{{ route('abrir_tesoreria_crown_jornada') }}" class="form-inline px-3 py-2 mb-0 bg-light" data-enter-campos="1">
                        @csrf
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        <label for="fecha" class="mr-2 mb-0">Fecha</label>
                        @php
                            $puedeSalto = ! empty($puedeSaltoJornada);
                            $minAbrir = $proximaFechaJornada ?? null;
                            $defaultAbrir = old('fecha', ($minAbrir && $minAbrir <= now()->toDateString()) ? $minAbrir : now()->toDateString());
                        @endphp
                        <input type="date" name="fecha" id="fecha" class="form-control form-control-sm mr-2" required
                               value="{{ $defaultAbrir }}"
                               @if ($minAbrir) min="{{ $minAbrir }}" @endif
                               @if (! $puedeSalto && $minAbrir)
                                   max="{{ $minAbrir }}"
                               @else
                                   max="{{ now()->toDateString() }}"
                               @endif>
                        <button type="submit" class="btn btn-success btn-sm">Abrir</button>
                        @if (! empty($ultimaFechaJornada))
                            <span class="text-muted small ml-2">
                                Última: {{ \Carbon\Carbon::parse($ultimaFechaJornada)->format('d/m/Y') }}
                                @if ($puedeSalto)
                                    · con permiso de salto podés avanzar más de un día
                                @else
                                    · solo el día siguiente (sin salto)
                                @endif
                            </span>
                        @endif
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
            {{-- Pocas columnas: caber en viewport (sin scroll lateral por nombres largos). --}}
            <div class="tabla-ancha-grilla" data-tabla-ancha>
                <div class="tabla-ancha-scroll-top" hidden>
                    <div class="tabla-ancha-scroll-top-inner"></div>
                </div>
                <div class="tabla-ancha-wrap card-body p-0 lw-table-wrap lw-table-fit">
                    <table class="tabla-ancha table table-sm table-striped table-bordered table-hover mb-0 lw-tabla-grilla" id="tabla-paginada">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                @foreach ($columnasVisibles as $keyColumna)
                                    @include('caja.tesoreria_crown.partials.workbench_th', [
                                        'key' => $keyColumna,
                                        'rutaListado' => 'tesoreria_crown_jornada',
                                        'thClass' => 'lw-col'.($keyColumna === 'cajeros' ? ' tc-col-cajeros' : ''),
                                    ])
                                @endforeach
                                <th class="lw-col-acciones" data-orderable="false">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($jornadas as $jornada)
                                @php
                                    $puedeBorrar = (int) $jornada->comprobantes_count === 0;
                                    $nombresCajeros = $jornada->asignaciones->pluck('cajero.nombre')->filter()->unique()->implode(', ');
                                @endphp
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        <td class="lw-col{{ $keyColumna === 'cajeros' ? ' tc-col-cajeros' : '' }}"
                                            data-col-key="{{ $keyColumna }}"
                                            @if ($keyColumna === 'cajeros' && $nombresCajeros !== '') title="{{ $nombresCajeros }}" @endif>
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
                                                    <span class="lw-celda-texto">{{ $nombresCajeros !== '' ? $nombresCajeros : '—' }}</span>
                                                    @break
                                            @endswitch
                                        </td>
                                    @endforeach
                                    <td class="lw-col-acciones text-nowrap">
                                        <div class="lw-acciones-inner">
                                            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-jornada', false))
                                                <a href="{{ route('ver_tesoreria_crown_jornada', ['id' => $jornada->id, 'abrir' => 1]) }}" class="btn-accion-tabla tooltipsC" title="Abrir caja">
                                                    <i class="fa fa-plus"></i>
                                                </a>
                                            @endif
                                            @if ($jornada->estaAbierta() && can('editar-tesoreria-crown-jornada', false))
                                                <form action="{{ route('cerrar_tesoreria_crown_jornada', $jornada->id) }}" method="POST" class="d-inline-flex form-eliminar mb-0" onsubmit="return confirm('¿Cerrar la jornada del {{ $jornada->fecha->format('d/m/Y') }}?');">
                                                    @csrf
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Cerrar">
                                                        <i class="fa fa-lock"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($puedeBorrar && can('editar-tesoreria-crown-jornada', false))
                                                <form action="{{ route('eliminar_tesoreria_crown_jornada', $jornada->id) }}" method="POST" class="d-inline-flex form-eliminar mb-0" onsubmit="return confirm('¿Eliminar la jornada del {{ $jornada->fecha->format('d/m/Y') }}? Se quitan las asignaciones de cajero.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                        <i class="fa fa-times-circle text-danger"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
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
{{-- Reordenar/ocultar columnas en thead; agregar = Diseñar vista. --}}
@include('includes.listado.workbench_columnas_dnd', [
    'rutaGuardarColumnasDnd' => 'guardar_columnas_tesoreria_crown_jornada',
])
@include('caja.tesoreria_crown.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_jornada',
    'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_jornada',
    'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_jornada',
    'rutaPreview' => 'preview_tesoreria_crown_jornada',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_jornada',
])
@endsection
