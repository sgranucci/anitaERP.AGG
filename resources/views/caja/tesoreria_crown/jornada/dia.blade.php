@extends("theme.$theme.layout")

@section('titulo')
    Jornada de tesorería
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/jornada.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/jornada.js')) }}"></script>
@endsection

@section('contenido')
@php
    $abrirCaja = request()->boolean('abrir') || $errors->any() || old('deposito_id') || old('cajero_crown_id');
@endphp
@include('includes.mensaje')
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
            @include('caja.tesoreria_crown.partials.sala_chips', ['ruta' => 'tesoreria_crown_jornada'])
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
                    <form method="POST" action="{{ route('abrir_tesoreria_crown_jornada') }}" class="form-inline mb-3">
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
                    <form method="POST" action="{{ route('asignar_tesoreria_crown_jornada', $jornada->id) }}" class="border rounded p-3" id="form-asignar-jornada" data-sala-id="{{ $sala->id }}">
                        @csrf
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Caja</label>
                                <div class="input-group">
                                    <input type="hidden" name="deposito_id" id="deposito_id" value="{{ old('deposito_id') }}">
                                    <input type="text" class="form-control" id="deposito_codigo" placeholder="Código" autocomplete="off" value="">
                                    <input type="text" class="form-control" id="deposito_nombre" readonly placeholder="Nombre" tabindex="-1">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="btn-consulta-deposito" title="Buscar caja"><i class="fa fa-search"></i></button>
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
                                        <button type="button" class="btn btn-outline-secondary" id="btn-consulta-cajero" title="Buscar cajero"><i class="fa fa-search"></i></button>
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
                        $pendientesFirma = $asignaciones->flatMap(function ($fila) {
                            return $fila->comprobantes
                                ->filter(fn ($doc) => $doc->estado === 'P')
                                ->each(fn ($doc) => $doc->setRelation('asignacion', $fila));
                        });
                        $borradoresMesa = collect($tableroMesas['movimientos'] ?? [])->where('estado', 'B')->count();
                        $movimientosBoveda = ($boveda['movimientos'] ?? collect())->count();
                        $urlEscritorio = function (string $panelElegido) use ($jornada): string {
                            return route('ver_tesoreria_crown_jornada', $jornada->id).'?'.http_build_query(['panel' => $panelElegido]);
                        };
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
                    <nav class="tc-desk-nav" aria-label="Escritorio de la jornada">
                        <a href="{{ $urlEscritorio('cajeros') }}" class="{{ $panel === 'cajeros' ? 'is-on' : '' }}">
                            Cajeros <span class="tc-count">{{ $asignaciones->count() }}</span>
                        </a>
                        @if ($puedeMesas)
                            <a href="{{ $urlEscritorio('mesas') }}#piso" class="{{ $panel === 'mesas' ? 'is-on' : '' }}">
                                Mesas
                                <span class="tc-count {{ $borradoresMesa > 0 ? 'is-alerta' : '' }}">{{ count($tableroMesas['mesas'] ?? []) }}</span>
                            </a>
                        @endif
                        @if ($puedeBoveda)
                            <a href="{{ $urlEscritorio('boveda') }}" class="{{ $panel === 'boveda' ? 'is-on' : '' }}">
                                Bóveda <span class="tc-count">{{ $movimientosBoveda }}</span>
                            </a>
                        @endif
                    </nav>

                    @if ($panel === 'mesas')
                        @include('caja.tesoreria_crown.jornada.partials.mesa')
                    @elseif ($panel === 'boveda')
                        @include('caja.tesoreria_crown.jornada.partials.boveda')
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Turno</th>
                                    <th>Caja</th>
                                    <th>Cajero</th>
                                    <th>Tarea</th>
                                    <th>Habilitación</th>
                                    <th>Rendición</th>
                                    <th>Cierre</th>
                                    @if ($jornada->estaAbierta())
                                        <th class="text-center" style="width:70px;">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($asignaciones as $fila)
                                    <tr>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownAsignacionCatalogo::turno($fila->turno) }}</td>
                                        <td>{{ $fila->deposito->codigo ?? '' }} — {{ $fila->deposito->nombre ?? '' }}</td>
                                        <td>{{ $fila->cajero->codigo ?? '' }} — {{ $fila->cajero->nombre ?? '' }}</td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownAsignacionCatalogo::tarea($fila->tipo_tarea) }}</td>
                                        @php
                                            $habilitaciones = $fila->comprobantes->filter(fn ($doc) => $doc->tipo === 'H' && $doc->revierte_id === null);
                                            $rendiciones = $fila->comprobantes->filter(fn ($doc) => $doc->tipo === 'F' && $doc->revierte_id === null);
                                            $hayHabilitacionConforme = $habilitaciones->contains(fn ($doc) => $doc->estado === 'C');
                                            $cajaCerrada = $fila->cierre !== null;
                                        @endphp
                                        <td>
                                            @foreach ($habilitaciones as $doc)
                                                <div><a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $doc->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($doc->numero) }} · {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($doc->estado) }}</a></div>
                                            @endforeach
                                            @if ($jornada->estaAbierta() && ! $cajaCerrada && can('crear-tesoreria-crown-movimiento', false))
                                                <form method="POST" action="{{ route('crear_tesoreria_crown_movimiento') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="asignacion_id" value="{{ $fila->id }}">
                                                    <input type="hidden" name="tipo" value="H">
                                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ $habilitaciones->isEmpty() ? 'Habilitar' : 'Otra habilitación' }}</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach ($rendiciones as $doc)
                                                <div><a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $doc->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($doc->numero) }} · {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($doc->estado) }}</a></div>
                                            @endforeach
                                            @if ($jornada->estaAbierta() && ! $cajaCerrada && $hayHabilitacionConforme && can('crear-tesoreria-crown-movimiento', false))
                                                <form method="POST" action="{{ route('crear_tesoreria_crown_movimiento') }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="asignacion_id" value="{{ $fila->id }}">
                                                    <input type="hidden" name="tipo" value="F">
                                                    <button type="submit" class="btn btn-link btn-sm p-0">{{ $rendiciones->isEmpty() ? 'Rendir' : 'Otra rendición' }}</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($cajaCerrada)
                                                <a class="text-primary" href="{{ route('ver_tesoreria_crown_cierre', $fila->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($fila->cierre->numero) }} · {{ $fila->cierre->estadoTexto() }}</a>
                                            @elseif ($jornada->estaAbierta())
                                                <a class="text-primary" href="{{ route('ver_tesoreria_crown_cierre', $fila->id) }}">Cerrar caja</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        @if ($jornada->estaAbierta())
                                            <td class="text-center">
                                                @if (! $cajaCerrada && $fila->comprobantes->isEmpty() && can('editar-tesoreria-crown-jornada', false))
                                                    <form action="{{ route('quitar_tesoreria_crown_asignacion', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Quitar esta asignación?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-accion-tabla" title="Quitar"><i class="fa fa-times-circle text-danger"></i></button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $jornada->estaAbierta() ? 8 : 7 }}" class="text-center text-muted py-3">No hay cajeros asignados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
