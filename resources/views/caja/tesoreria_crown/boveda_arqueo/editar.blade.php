@extends("theme.$theme.layout")

@section('titulo')
    Arqueo de turno {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible((int) $arqueo->numero) }}
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@if ($escritorio)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'navActivo' => 'boveda',
        'puedeMesas' => $escritorio['puedeMesas'],
        'puedeBoveda' => $escritorio['puedeBoveda'],
        'navAsignaciones' => $escritorio['asignaciones'],
        'navMesas' => $escritorio['mesas'],
        'navBorradoresMesa' => $escritorio['borradoresMesa'],
        'navBoveda' => $escritorio['movimientosBoveda'],
        'puedeSlots' => $escritorio['puedeSlots'],
        'puedePoder' => $escritorio['puedePoder'],
        'puedeAnexo' => $escritorio['puedeAnexo'],
        'puedeKardex' => $escritorio['puedeKardex'] ?? false,
        'navSlots' => $escritorio['slots'],
    ])
@endif
@php
    $numero = \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible((int) $arqueo->numero);
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Arqueo {{ $numero }}
                    · {{ $jornada->fecha->format('d/m/Y') }}
                    — {{ $lugar->codigo }} {{ $lugar->nombre }}
                    <span class="badge badge-{{ $arqueo->estaConforme() ? 'success' : ($arqueo->estaPendiente() ? 'warning' : 'secondary') }}">
                        {{ \App\Support\Caja\TesoreriaCrownBovedaArqueoSupport::etiquetaEstado($arqueo->estado) }}
                    </span>
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda_arqueo', [
                        'sala_id' => $sala->id,
                        'jornada_id' => $jornada->id,
                        'deposito_id' => $lugar->id,
                    ]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-reply-all"></i> Listado
                    </a>
                    <a href="{{ route('pdf_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Corte: <strong>{{ $arqueo->corte_at?->format('d/m/Y H:i') }}</strong>
                    @if ($arqueo->anterior)
                        · Anterior: {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible((int) $arqueo->anterior->numero) }}
                    @endif
                    @if ($arqueo->observacion)
                        · {{ $arqueo->observacion }}
                    @endif
                </p>

                @include('caja.tesoreria_crown.boveda_arqueo.partials.tabla_lineas', ['grupos' => $grupos])

                <div class="row mt-3">
                    @foreach ([
                        'entrega' => ['Entrega (sale de turno)', $arqueo->entregaUsuario, $arqueo->entrega_at],
                        'recibe' => ['Recepción (entra de turno)', $arqueo->recibeUsuario, $arqueo->recibe_at],
                    ] as $lado => [$etiqueta, $firmante, $cuando])
                        <div class="col-md-6 mb-3">
                            <div class="card card-outline card-secondary h-100">
                                <div class="card-header py-2">
                                    <strong>{{ $etiqueta }}</strong>
                                </div>
                                <div class="card-body py-2">
                                    @if ($firmante)
                                        <p class="mb-0">
                                            {{ $firmante->nombre }}
                                            <span class="text-muted">({{ $firmante->usuario }})</span>
                                            · {{ $cuando?->format('d/m/Y H:i') }}
                                        </p>
                                    @elseif ($puedeFirmar)
                                        <form method="POST" action="{{ route('firmar_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" data-enter-campos="1">
                                            @csrf
                                            <input type="hidden" name="lado" value="{{ $lado }}">
                                            <div class="form-row align-items-end">
                                                <div class="col-sm-4 mb-2">
                                                    <label class="mb-0 small">Usuario</label>
                                                    <input type="text" name="firma_usuario" class="form-control form-control-sm" placeholder="Usuario" autocomplete="off" required>
                                                </div>
                                                <div class="col-sm-4 mb-2">
                                                    <label class="mb-0 small">Clave</label>
                                                    <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                                </div>
                                                <div class="col-sm-4 mb-2">
                                                    <button type="submit" class="btn btn-primary btn-sm btn-block">Firmar</button>
                                                </div>
                                            </div>
                                        </form>
                                    @else
                                        <p class="text-muted mb-0">Sin firma.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2">
                    @if ($puedePedirFirmas)
                        <form method="POST" action="{{ route('pedir_firmas_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fa fa-pen"></i> Pedir firmas
                            </button>
                        </form>
                    @endif
                    @if ($puedeEliminar)
                        <form method="POST" action="{{ route('eliminar_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" class="d-inline"
                              onsubmit="return confirm('¿Eliminar este borrador de arqueo?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-times-circle"></i> Eliminar borrador
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
