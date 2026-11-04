@extends("theme.$theme.layout")

@section('titulo')
    Jornada de tesorería
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/jornada.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/jornada.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-agrupar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-disenador-preview.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-vista-guardar.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/workbench.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/workbench.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/mesa_revertir.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/mesa_revertir.js')) }}"></script>
@endsection

@section('contenido')
@php
    $abrirCaja = request()->boolean('abrir') || $errors->any() || old('deposito_id') || old('cajero_crown_id');
@endphp
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
@if ($jornada)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'navActivo' => $panel,
        'navAsignaciones' => $asignacionesTodas->count(),
        'navMesas' => count($tableroMesas['mesas'] ?? []),
        'navBorradoresMesa' => collect($tableroMesas['movimientos'] ?? [])->where('estado', 'B')->count(),
        'navBoveda' => ($boveda['movimientos'] ?? collect())->count(),
        'puedeSlots' => $puedeSlots ?? false,
        'navSlots' => $navSlots ?? 0,
    ])
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Jornada {{ $jornada->fecha->format('d/m/Y') }}</h3>
                <div class="card-tools">
                    @if ($jornada->estaAbierta() && can('crear-tesoreria-crown-jornada', false))
                        <button type="button" class="btn btn-primary btn-sm mr-1" data-toggle="collapse" data-target="#panel-abrir-caja" aria-expanded="{{ $abrirCaja ? 'true' : 'false' }}">
                            Abrir caja
                        </button>
                    @endif
                    <a href="{{ route('tesoreria_crown_jornada', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            @php
                $exportDeskRuta = null;
                $exportDeskParams = [];
                if ($jornada) {
                    $exportDeskRuta = match ($panel ?? '') {
                        'mesas' => 'listar_mesas_tesoreria_crown_jornada',
                        'slots' => 'listar_slots_tesoreria_crown_jornada',
                        'poder' => 'listar_poder_publico_tesoreria_crown_jornada',
                        'anexo' => 'listar_anexo1_tesoreria_crown_jornada',
                        default => 'listar_cajeros_tesoreria_crown_jornada',
                    };
                    $exportDeskParams = ($panel ?? '') === 'cajeros'
                        ? array_merge($filtrosQuery ?? ['id' => $jornada->id, 'panel' => 'cajeros'], [
                            'columnas' => implode(',', $columnasVisibles ?? []),
                        ])
                        : ['id' => $jornada->id];
                }
            @endphp
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_jornada',
                'exportRuta' => $exportDeskRuta,
                'exportQueryparams' => $exportDeskParams,
            ])
            <div class="card-body">
                <p class="text-muted mb-3">
                    La jornada es el día de trabajo de la sala. Con la jornada abierta se asigna un cajero a una caja, en un turno, con la tarea que va a hacer.
                </p>

                @if ($jornada)
                    <div class="d-flex flex-wrap align-items-center mb-3">
                        <h4 class="mb-0 mr-3">{{ $jornada->fecha->format('d/m/Y') }}</h4>
                        <span class="badge {{ $jornada->estaAbierta() ? 'badge-success' : 'badge-secondary' }} mr-3">
                            {{ $jornada->estaAbierta() ? 'Abierta' : 'Cerrada' }}
                        </span>
                        @if ($jornada->estaAbierta() && can('editar-tesoreria-crown-jornada', false))
                            <form action="{{ route('cerrar_tesoreria_crown_jornada', $jornada->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Cerrar esta jornada?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Cerrar jornada</button>
                            </form>
                        @endif
                    </div>
                @elseif ($sala && can('crear-tesoreria-crown-jornada', false))
                    <form method="POST" action="{{ route('abrir_tesoreria_crown_jornada') }}" class="form-inline mb-3" data-enter-campos="1">
                        @csrf
                        <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                        <label for="fecha" class="mr-2">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control mr-2" required value="{{ old('fecha', now()->toDateString()) }}" max="{{ now()->toDateString() }}">
                        <button type="submit" class="btn btn-success">Abrir jornada</button>
                    </form>
                @else
                    <p class="text-muted">No hay jornada en esta sala.</p>
                @endif

                @if ($abierta && (! $jornada || (int) $jornada->id !== (int) $abierta->id))
                    <p class="mb-3">
                        <a class="text-primary" href="{{ route('ver_tesoreria_crown_jornada', $abierta->id) }}">
                            Hay una jornada abierta el {{ $abierta->fecha->format('d/m/Y') }}
                        </a>
                    </p>
                @endif

                @if ($jornada && $jornada->estaAbierta() && can('crear-tesoreria-crown-jornada', false))
                    <div class="collapse {{ $abrirCaja ? 'show' : '' }} mb-3" id="panel-abrir-caja">
                    <form method="POST" action="{{ route('asignar_tesoreria_crown_jornada', $jornada->id) }}" class="border rounded p-3" id="form-asignar-jornada" data-sala-id="{{ $sala->id }}" data-enter-campos="1">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Caja</label>
                                <div class="input-group">
                                    <input type="hidden" name="deposito_id" id="deposito_id" value="{{ old('deposito_id') }}">
                                    <input type="text" class="form-control" id="deposito_codigo" placeholder="Código" autocomplete="off" value="">
                                    <input type="text" class="form-control" id="deposito_nombre" readonly placeholder="Nombre" tabindex="-1">
                                    <div class="input-group-append">
                                        <button type="button" class="btn-accion-tabla tooltipsC" id="btn-consulta-deposito" title="Buscar caja"><i class="fa fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Cajero</label>
                                <div class="input-group">
                                    <input type="hidden" name="cajero_crown_id" id="cajero_crown_id" value="{{ old('cajero_crown_id') }}">
                                    <input type="text" class="form-control" id="cajero_codigo" placeholder="Código" autocomplete="off">
                                    <input type="text" class="form-control" id="cajero_nombre" readonly placeholder="Nombre" tabindex="-1">
                                    <div class="input-group-append">
                                        <button type="button" class="btn-accion-tabla tooltipsC" id="btn-consulta-cajero" title="Buscar cajero"><i class="fa fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="turno">Turno</label>
                                <select name="turno" id="turno" class="form-control" required>
                                    @foreach ($turnos as $codigo => $nombre)
                                        <option value="{{ $codigo }}" @selected(old('turno') === $codigo)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="tipo_tarea">Tarea</label>
                                <select name="tipo_tarea" id="tipo_tarea" class="form-control" required>
                                    @foreach ($tareas as $codigo => $nombre)
                                        <option value="{{ $codigo }}" @selected(old('tipo_tarea', 'T') === $codigo)>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="btn-asignar-jornada">Asignar</button>
                    </form>
                    </div>
                @endif

                @if ($jornada)
                    @php
                        $pendientesFirma = $asignacionesTodas->flatMap(function ($fila) {
                            return $fila->comprobantes
                                ->filter(fn ($doc) => $doc->estado === 'P')
                                ->each(fn ($doc) => $doc->setRelation('asignacion', $fila));
                        });
                    @endphp
                    @if ($pendientesFirma->isNotEmpty())
                        <div class="tc-aviso">
                            <strong>Firmas</strong>
                            @foreach ($pendientesFirma as $doc)
                                <a href="{{ route('editar_tesoreria_crown_movimiento', $doc->id) }}">
                                    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($doc->tipo) }}
                                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($doc->numero) }}
                                    · {{ $doc->asignacion->deposito->codigo ?? '' }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($panel === 'mesas')
                        @include('caja.tesoreria_crown.jornada.partials.mesa')
                    @elseif ($panel === 'slots')
                        @include('caja.tesoreria_crown.jornada.partials.slots')
                    @elseif ($panel === 'poder')
                        @include('caja.tesoreria_crown.jornada.partials.poder_publico')
                    @elseif ($panel === 'anexo')
                        @include('caja.tesoreria_crown.jornada.partials.anexo1')
                    @else
                    @php
                        $muestraAcciones = $jornada->estaAbierta() || ($esAdministrador ?? false);
                    @endphp
                    <div class="lw-workbench mb-2" data-form="#form-filtros-tesoreria-crown-cajeros">
                        <form method="get" action="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" id="form-filtros-tesoreria-crown-cajeros" class="mb-0">
                            <input type="hidden" name="id" value="{{ $jornada->id }}">
                            <input type="hidden" name="panel" value="cajeros">
                            <input type="hidden" name="vista_id" value="{{ $vistaActiva->id ?? '' }}">
                            <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                            <input type="hidden" name="filtro_busqueda_rapida" id="filtro_busqueda_rapida" value="">
                            <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles ?? []) }}">
                            @include('caja.tesoreria_crown.partials.workbench_barra', [
                                'rutaListado' => 'ver_tesoreria_crown_jornada',
                                'baseUrl' => route('ver_tesoreria_crown_jornada', $jornada->id),
                                'limpiarParams' => ['id' => $jornada->id, 'panel' => 'cajeros', 'vista_estandar' => 1],
                            ])
                            @include('caja.tesoreria_crown.partials.workbench_qbe')
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    @foreach ($columnasVisibles as $keyColumna)
                                        @include('caja.tesoreria_crown.partials.workbench_th', ['key' => $keyColumna, 'rutaListado' => 'ver_tesoreria_crown_jornada'])
                                    @endforeach
                                    @if ($muestraAcciones)
                                        <th class="text-center" style="width:110px;">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($asignaciones as $fila)
                                    @php
                                        $idsRevertidos = $fila->comprobantes->pluck('revierte_id')->filter()->map(fn ($id) => (int) $id)->all();
                                        $papeles = function (string $tipo) use ($fila) {
                                            $docs = $fila->comprobantes->filter(fn ($doc) => $doc->tipo === $tipo)->sortBy('numero')->values();
                                            $reversas = $docs->filter(fn ($doc) => $doc->revierte_id !== null)->groupBy(fn ($doc) => (int) $doc->revierte_id);
                                            $orden = collect();
                                            foreach ($docs->filter(fn ($doc) => $doc->revierte_id === null) as $doc) {
                                                $orden->push($doc);
                                                foreach ($reversas->get((int) $doc->id, collect()) as $reversa) {
                                                    $orden->push($reversa);
                                                }
                                            }

                                            return $orden;
                                        };
                                        $habilitaciones = $papeles('H');
                                        $rendiciones = $papeles('F');
                                        $hayHabilitacionConforme = $fila->comprobantes->contains(function ($doc) use ($idsRevertidos) {
                                            return $doc->tipo === 'H'
                                                && $doc->estado === 'C'
                                                && $doc->revierte_id === null
                                                && ! in_array((int) $doc->id, $idsRevertidos, true);
                                        });
                                        $cajaCerrada = $fila->cierre !== null;
                                    @endphp
                                    <tr>
                                        @foreach ($columnasVisibles as $keyColumna)
                                            <td>
                                                @include('caja.tesoreria_crown.jornada.partials.cajero_celda', ['key' => $keyColumna])
                                            </td>
                                        @endforeach
                                        @if ($muestraAcciones)
                                            <td class="text-center">
                                                @if ($jornada->estaAbierta() && ! $cajaCerrada && $fila->comprobantes->isEmpty() && $fila->cierres->isEmpty() && can('editar-tesoreria-crown-jornada', false))
                                                    <form action="{{ route('quitar_tesoreria_crown_asignacion', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Quitar esta asignación?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Quitar"><i class="fa fa-times-circle text-danger"></i></button>
                                                    </form>
                                                @endif
                                                @if (($esAdministrador ?? false) && ($fila->comprobantes->isNotEmpty() || $fila->cierres->isNotEmpty()))
                                                    <form action="{{ route('vaciar_tesoreria_crown_asignacion', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Borrar todo de esta caja? Se eliminan habilitaciones, rendiciones, reversas y cierres. El cajero sigue asignado.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-accion-tabla tooltipsC" title="Borrar todo"><i class="fa fa-times-circle text-danger"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columnasVisibles) + ($muestraAcciones ? 1 : 0) }}" class="text-center text-muted py-3">{{ $tieneCriterios ? 'No hay cajeros para esta búsqueda.' : 'No hay cajeros asignados.' }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('caja.tesoreria_crown.partials.workbench_modales', [
                        'rutaGuardarColumnas' => 'guardar_columnas_tesoreria_crown_cajero',
                        'rutaGuardarVista' => 'guardar_vista_tesoreria_crown_cajero',
                        'rutaEliminarVista' => 'eliminar_vista_tesoreria_crown_cajero',
                        'rutaPreview' => 'preview_tesoreria_crown_cajero',
                        'rutaGuardarEtiquetas' => 'guardar_etiquetas_tesoreria_crown_cajero',
                    ])
                    @endif
                @endif
            </div>
            @if ($recientes->isNotEmpty())
                <div class="card-footer">
                    <span class="text-muted small mr-2">Jornadas:</span>
                    @foreach ($recientes as $item)
                        <a class="{{ $jornada && (int) $jornada->id === (int) $item->id ? 'font-weight-bold' : '' }} mr-2" href="{{ route('ver_tesoreria_crown_jornada', $item->id) }}?panel={{ $panel }}">
                            {{ $item->fecha->format('d/m/Y') }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modal-consulta-jornada" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-consulta-jornada-titulo">Buscar</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-2">
                    <input type="text" class="form-control" id="modal-consulta-jornada-q" placeholder="Código o nombre">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-info" id="modal-consulta-jornada-buscar">Buscar</button>
                    </div>
                </div>
                <p class="small text-muted mb-2" id="modal-consulta-jornada-total"></p>
                <div class="table-responsive" style="max-height:60vh;overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;">
                            <tr id="modal-consulta-jornada-cabecera"><th>Código</th><th>Nombre</th><th></th></tr>
                        </thead>
                        <tbody id="modal-consulta-jornada-filas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
