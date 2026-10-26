@extends("theme.$theme.layout")

@section('titulo')
    Cierre de caja
@endsection

@section('contenido')
@include('includes.mensaje')
@php
    $pesos = fn ($importe) => number_format((float) $importe, 2, ',', '.');
    $numeros = $cierre ?? null;
    $habilitado = $numeros ? $numeros->efectivo_habilitado : $calculo['habilitado'];
    $rendido = $numeros ? $numeros->valores_rendidos : $calculo['rendido'];
    $resultado = $numeros ? $numeros->resultado : $calculo['resultado'];
    $diferencia = $numeros ? $numeros->diferencia : $calculo['diferencia'];
    $signo = \App\Support\Caja\TesoreriaCrownCierreSupport::signo((float) $diferencia);
    $abierta = $asignacion->jornada->estaAbierta();
    $estado = $cierre === null ? '' : $cierre->estado;
    $puedeMover = $cierre && in_array($estado, [
        \App\Support\Caja\TesoreriaCrownCierreSupport::CONFORME,
        \App\Support\Caja\TesoreriaCrownCierreSupport::NO_CONFORME,
        \App\Support\Caja\TesoreriaCrownCierreSupport::REVISION,
    ], true);
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Cierre de caja
                    @if ($cierre)
                        {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($cierre->numero) }}
                    @endif
                    · {{ $asignacion->jornada->fecha->format('d/m/Y') }}
                    — {{ $asignacion->deposito->codigo ?? '' }} {{ $asignacion->deposito->nombre ?? '' }}
                    — {{ $asignacion->cajero->nombre ?? '' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_tesoreria_crown_jornada', $asignacion->jornada_id) }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-reply-all"></i> Volver a la jornada
                    </a>
                </div>
            </div>
            <div class="card-body">
                @if ($cierre)
                    <p class="mb-3">Estado: <strong>{{ $cierre->estadoTexto() }}</strong></p>
                @elseif (! $calculo['puede'])
                    <div class="alert alert-warning">{{ $calculo['motivo'] }}</div>
                @elseif ($calculo['supera'])
                    <div class="alert alert-warning">La diferencia supera la tolerancia de {{ $pesos($calculo['tolerancia']) }}. El cierre va a quedar en auditoría.</div>
                @endif

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0" style="max-width:36rem;">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Concepto</th><th class="text-right" style="width:10rem;">Pesos</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Efectivo habilitado</td><td class="text-right">{{ $pesos($habilitado) }}</td></tr>
                            <tr><td>Valores rendidos</td><td class="text-right">{{ $pesos($rendido) }}</td></tr>
                            <tr><td>Resultado (gastronomía / hotel)</td><td class="text-right">{{ $pesos($resultado) }}</td></tr>
                            <tr>
                                <td><strong>Diferencia · {{ $signo }}</strong></td>
                                <td class="text-right"><strong>{{ $pesos($diferencia) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted">La diferencia es valores rendidos menos efectivo habilitado menos el resultado. Un número negativo es faltante. Dólares y euros se pasan a pesos con la cotización de la jornada. El resultado sale del X y las notas de crédito, y en hotel suma la cuenta corriente.</p>

                @if ($cierre)
                    <p class="mb-1">Tesorería: {{ $cierre->tesoreriaUsuario->nombre ?? '' }} @if ($cierre->tesoreria_at) · {{ $cierre->tesoreria_at->format('d/m/Y H:i') }} @endif</p>
                    <p class="mb-1">Cajero: {{ $asignacion->cajero->nombre ?? '' }} @if ($cierre->cajero_at) · {{ $cierre->cajero_at->format('d/m/Y H:i') }} @endif</p>
                    @if ($cierre->auditor_at)
                        <p class="mb-3">Auditoría: {{ $cierre->auditorUsuario->nombre ?? '' }} · {{ $cierre->auditor_at->format('d/m/Y H:i') }}</p>
                    @endif
                    @if ($cierre->observacion)
                        <p>{{ $cierre->observacion }}</p>
                    @endif
                @endif

                @if (! $cierre && $calculo['puede'] && $abierta && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('cerrar_tesoreria_crown_cierre', $asignacion->id) }}" class="form-horizontal form--label-right">
                        @csrf
                        @include('includes.form-error')
                        <div class="form-group row">
                            <label class="col-lg-4 control-label text-right pr-2">Resultado del cierre</label>
                            <div class="col-lg-8">
                                <label class="kt-radio d-block mb-1 font-weight-normal">
                                    <input type="radio" name="estado" value="C" @checked(old('estado', 'C') === 'C') required> Conforme
                                </label>
                                <label class="kt-radio d-block mb-1 font-weight-normal">
                                    <input type="radio" name="estado" value="R" @checked(old('estado') === 'R')> A revisión
                                </label>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="observacion" class="col-lg-4 control-label text-right pr-2">Observación</label>
                            <div class="col-lg-8">
                                <input type="text" name="observacion" id="observacion" class="form-control" maxlength="500" value="{{ old('observacion') }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="firma_usuario" class="col-lg-4 control-label text-right pr-2">Tesorería</label>
                            <div class="col-lg-8">
                                <div class="form-row">
                                    <div class="col-md-6 mb-2">
                                        <input type="text" name="firma_usuario" id="firma_usuario" class="form-control" placeholder="Usuario" value="{{ old('firma_usuario') }}" autocomplete="username" required>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <input type="password" name="firma_password" class="form-control" placeholder="Clave" autocomplete="current-password" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="clave_cajero" class="col-lg-4 control-label text-right pr-2">Cajero</label>
                            <div class="col-lg-8">
                                <input type="password" name="clave_cajero" id="clave_cajero" class="form-control" placeholder="Clave del cajero" inputmode="numeric" autocomplete="off" required style="max-width:16rem;">
                                <small class="form-text text-muted">El PIN inicial es el código de este cajero.</small>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Cerrar caja</button>
                    </form>
                @endif

                @if ($puedeMover && can('editar-tesoreria-crown-movimiento', false))
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('revisar_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3">
                                @csrf
                                <h4 class="h6">Marcar revisada</h4>
                                <input type="text" name="observacion" class="form-control form-control-sm mb-2" maxlength="500" placeholder="Observación">
                                <input type="text" name="firma_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario de tesorería" autocomplete="username" required>
                                <input type="password" name="firma_password" class="form-control form-control-sm mb-2" placeholder="Clave" autocomplete="current-password" required>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Revisada</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('auditar_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3">
                                @csrf
                                <h4 class="h6">Enviar a auditoría</h4>
                                <input type="text" name="observacion" class="form-control form-control-sm mb-2" maxlength="500" placeholder="Observación">
                                <input type="text" name="firma_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario de tesorería" autocomplete="username" required>
                                <input type="password" name="firma_password" class="form-control form-control-sm mb-2" placeholder="Clave" autocomplete="current-password" required>
                                <button type="submit" class="btn btn-outline-primary btn-sm">A auditoría</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($estado === \App\Support\Caja\TesoreriaCrownCierreSupport::AUDITORIA && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('firmar_auditoria_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3" style="max-width:36rem;">
                        @csrf
                        <h4 class="h6">Cierre de auditoría</h4>
                        <p class="text-muted">Lo firma otra persona, distinta de quien mandó el caso a auditoría.</p>
                        <input type="text" name="observacion" class="form-control form-control-sm mb-2" maxlength="500" placeholder="Observación">
                        <input type="text" name="firma_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario" autocomplete="username" required>
                        <input type="password" name="firma_password" class="form-control form-control-sm mb-2" placeholder="Clave" autocomplete="current-password" required>
                        <button type="submit" class="btn btn-primary btn-sm">Cerrar auditoría</button>
                    </form>
                @endif

                @if ($cierre && $cierre->eventos->isNotEmpty())
                    <h4 class="h6 mt-3">Historia</h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr><th>Cuándo</th><th>Estado</th><th>Quién</th><th>Detalle</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($cierre->eventos->sortBy('id') as $evento)
                                    <tr>
                                        <td>{{ $evento->created_at?->format('d/m/Y H:i') }}</td>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownCierreSupport::estado($evento->estado) }}</td>
                                        <td>{{ $evento->usuario->nombre ?? '' }}</td>
                                        <td>{{ $evento->detalle }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
