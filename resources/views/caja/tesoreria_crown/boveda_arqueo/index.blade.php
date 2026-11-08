@extends("theme.$theme.layout")

@section('titulo')
    Arqueo de turno — Bóveda
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Arqueo de cambio de turno
                    @if ($jornada)
                        · {{ $jornada->fecha->format('d/m/Y') }}
                    @endif
                    @if ($lugar)
                        — {{ $lugar->codigo }} {{ $lugar->nombre }}
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_boveda', [
                        'sala_id' => $sala->id ?? null,
                        'jornada_id' => $jornada->id ?? null,
                        'deposito_id' => $lugar->id ?? null,
                    ]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-reply-all"></i> Bóveda
                    </a>
                    @if ($jornada && $lugar && $jornada->estaAbierta() && can('crear-tesoreria-crown-boveda-arqueo', false))
                        <a href="{{ route('nueva_tesoreria_crown_boveda_arqueo', ['jornada_id' => $jornada->id, 'deposito_id' => $lugar->id]) }}"
                           class="btn btn-primary btn-sm">
                            <i class="fa fa-plus-circle"></i> Nuevo arqueo
                        </a>
                    @endif
                    @if ($jornada)
                        <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=boveda" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-desktop"></i> Escritorio
                        </a>
                    @endif
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_boveda_arqueo',
            ])
            @include('caja.tesoreria_crown.partials.jornada_selector', [
                'ruta' => 'tesoreria_crown_boveda_arqueo',
                'queryExtra' => array_filter([
                    'deposito_id' => $lugar->id ?? null,
                ]),
            ])
            <div class="card-body">
                @if (! $jornada)
                    <p>
                        @if (trim((string) ($fechaPedida ?? '')) !== '')
                            No hay jornada el {{ \Illuminate\Support\Carbon::parse($fechaPedida)->format('d/m/Y') }} en esta sala.
                        @else
                            Esta sala no tiene jornada.
                        @endif
                    </p>
                @elseif ($lugares->isEmpty())
                    <div class="alert alert-warning mb-0">No hay depósito de bóveda en esta sala.</div>
                @else
                    @if ($lugares->count() > 1)
                        <div class="mb-3">
                            @foreach ($lugares as $dep)
                                <a href="{{ route('tesoreria_crown_boveda_arqueo', [
                                    'sala_id' => $sala->id,
                                    'jornada_id' => $jornada->id,
                                    'deposito_id' => $dep->id,
                                ]) }}"
                                   class="btn btn-sm {{ (int) $dep->id === (int) $lugar->id ? 'btn-info' : 'btn-outline-info' }}">
                                    {{ $dep->codigo }} {{ $dep->nombre }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                    @if ($arqueos->isEmpty())
                        <p class="text-muted mb-0">No hay arqueos de turno para este depósito en la jornada.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Nº</th>
                                        <th>Corte</th>
                                        <th>Estado</th>
                                        <th>Entrega</th>
                                        <th>Recepción</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($arqueos as $arqueo)
                                        <tr>
                                            <td>
                                                <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda_arqueo', $arqueo->id) }}">
                                                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible((int) $arqueo->numero) }}
                                                </a>
                                            </td>
                                            <td>{{ $arqueo->corte_at?->format('d/m/Y H:i') }}</td>
                                            <td>{{ \App\Support\Caja\TesoreriaCrownBovedaArqueoSupport::etiquetaEstado($arqueo->estado) }}</td>
                                            <td>
                                                @if ($arqueo->entregaUsuario)
                                                    {{ $arqueo->entregaUsuario->nombre }}
                                                    <span class="text-muted small">{{ $arqueo->entrega_at?->format('H:i') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>
                                                @if ($arqueo->recibeUsuario)
                                                    {{ $arqueo->recibeUsuario->nombre }}
                                                    <span class="text-muted small">{{ $arqueo->recibe_at?->format('H:i') }}</span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <a class="btn-accion-tabla" href="{{ route('editar_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" title="Ver">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a class="btn-accion-tabla" href="{{ route('pdf_tesoreria_crown_boveda_arqueo', $arqueo->id) }}" target="_blank" rel="noopener" title="PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
