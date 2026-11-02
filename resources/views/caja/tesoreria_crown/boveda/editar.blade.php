@extends("theme.$theme.layout")

@section('titulo')
    Movimiento de bóveda
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('contenido')
@include('includes.mensaje')
@if ($movimiento->jornada)
    @include('caja.tesoreria_crown.jornada.partials.desk_nav', [
        'jornada' => $movimiento->jornada,
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
        'navSlots' => $escritorio['slots'],
    ])
@endif
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Movimiento {{ $numero }}
                    · {{ $fecha }}
                    — {{ $movimiento->deposito->codigo ?? '' }} {{ $movimiento->deposito->nombre ?? '' }}
                </h3>
                <div class="card-tools d-flex align-items-center" style="gap:.35rem;">
                    @include('caja.tesoreria_crown.boveda.partials.cambios_dia')
                    @if (can('imprimir-tesoreria-crown-boveda', false))
                        <a href="{{ route('pdf_tesoreria_crown_boveda', $movimiento->id) }}" class="btn btn-outline-light btn-sm" target="_blank" rel="noopener">
                            <i class="fa fa-print"></i> Imprimir
                        </a>
                    @endif
                    <a href="{{ route('tesoreria_crown_boveda', ['sala_id' => $movimiento->sala_id, 'jornada_id' => $movimiento->jornada_id, 'deposito_id' => $movimiento->deposito_id]) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a la bóveda
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="mb-3">
                    Estado: <strong>{{ \App\Support\Caja\TesoreriaCrownBovedaSupport::estado($movimiento->estado) }}</strong>
                    @if ($movimiento->confirmado_at)
                        · {{ \App\Support\Caja\TesoreriaCrownFirmaSupport::textoFirma($movimiento->usuario, $movimiento->confirmado_at) }}
                    @endif
                </p>
                @if ($movimiento->comprobante)
                    <p>
                        Vinculado a
                        <a class="text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $movimiento->comprobante_id) }}">
                            {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($movimiento->comprobante->tipo) }}
                            {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->comprobante->numero) }}
                        </a>
                    </p>
                @endif
                @include('includes.form-error')
                <form method="POST" action="{{ route('guardar_tesoreria_crown_boveda', $movimiento->id) }}" class="form-horizontal form--label-right" data-enter-campos="1" data-enter-boton="1" autocomplete="off">
                    @csrf
                    <input type="hidden" name="sala_id" value="{{ $movimiento->sala_id }}">
                    @include('caja.tesoreria_crown.boveda.partials.form_carga', [
                        'urlConfirmar' => $editable && $puedeConfirmar ? route('confirmar_tesoreria_crown_boveda', $movimiento->id) : null,
                    ])
                </form>
                @if ($editable && can('crear-tesoreria-crown-boveda', false))
                    <form method="POST" action="{{ route('eliminar_tesoreria_crown_boveda', $movimiento->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar borrador</button>
                    </form>
                @endif
                @if ($movimiento->reversa)
                    <div class="border rounded p-3 mt-3">
                        <p class="mb-1">Revertido por el movimiento
                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $movimiento->reversa->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->reversa->numero) }}</a>.
                        </p>
                        @if ($movimiento->reversa->motivo)
                            <p class="mb-0">Motivo: {{ $movimiento->reversa->motivo }}</p>
                        @endif
                    </div>
                @elseif ($movimiento->revierte)
                    <div class="border rounded p-3 mt-3">
                        <p class="mb-1">Reversa del movimiento
                            <a class="text-primary" href="{{ route('editar_tesoreria_crown_boveda', $movimiento->revierte->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($movimiento->revierte->numero) }}</a>.
                        </p>
                        @if ($movimiento->motivo)
                            <p class="mb-0">Motivo: {{ $movimiento->motivo }}</p>
                        @endif
                    </div>
                @elseif ($movimiento->comprobante_id && $movimiento->estado === 'C')
                    <div class="border rounded p-3 mt-3">
                        <p class="mb-0">Este movimiento lo generó el comprobante del cajero. Para deshacerlo, revertí ese comprobante.</p>
                    </div>
                @elseif ($movimiento->puedeRevertir() && $movimiento->jornada->estaAbierta() && can('editar-tesoreria-crown-boveda', false))
                    <form method="POST" action="{{ route('revertir_tesoreria_crown_boveda', $movimiento->id) }}" class="border rounded p-3 mt-3" autocomplete="off" data-enter-campos="1" data-enter-boton="1">
                        @csrf
                        <p class="mb-2">Se graba otro movimiento, con el número siguiente y las cantidades al revés. El original queda.</p>
                        <div class="form-group">
                            <label class="control-label text-right pr-2" for="boveda-motivo-reversion">Motivo</label>
                            <textarea name="motivo" id="boveda-motivo-reversion" class="form-control" rows="2" maxlength="500" required></textarea>
                        </div>
                        <div class="form-row">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="firma_usuario" class="form-control" placeholder="Usuario" autocomplete="off" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="new-password" required>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Revertir</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaempleado_sueldos')
@include('includes.ventas.modalconsultacliente')
@include('includes.caja.modalconsultabovedatipo')
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/sueldos/empleado/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/sueldos/empleado/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/efeboveda.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/efeboveda.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/boveda_tipo_consulta.js')) }}"></script>
@endsection
