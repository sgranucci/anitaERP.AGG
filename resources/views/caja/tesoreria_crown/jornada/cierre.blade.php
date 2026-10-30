@extends("theme.$theme.layout")

@section('titulo')
    Cierre de caja
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
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
    $detalle = is_array($cierre->componentes ?? null)
        ? ($cierre->componentes['filas'] ?? null)
        : ($cierre ? null : ($calculo['componentes']['filas'] ?? []));
    $anteriores = $anteriores ?? collect();
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
                @if ($anteriores->isNotEmpty())
                    <div class="tc-cierre-historia">
                        @foreach ($anteriores as $anterior)
                            <article class="tc-cierre-anterior">
                                <div class="tc-papel is-reabierto">
                                    <span class="tc-papel-nro">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($anterior->numero) }}</span>
                                    <span class="tc-papel-marca is-reabierto"><i class="fa fa-unlock-alt"></i> Reabierto</span>
                                    <span class="tc-papel-estado">{{ $anterior->estadoTexto() }}</span>
                                </div>
                                <p class="tc-cierre-meta">
                                    {{ $anterior->reabiertaUsuario->nombre ?? '' }}
                                    @if ($anterior->reabierta_at)
                                        · {{ $anterior->reabierta_at->format('d/m/Y H:i') }}
                                    @endif
                                </p>
                                @if ($anterior->reabierta_motivo)
                                    <p class="tc-cierre-motivo">{{ $anterior->reabierta_motivo }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
                @if ($cierre && $anteriores->isNotEmpty())
                    <div class="tc-papel mb-3">
                        <span class="tc-papel-marca is-vigente">Vigente</span>
                        <span class="tc-papel-estado">{{ $cierre->estadoTexto() }}</span>
                    </div>
                @elseif ($cierre)
                    <p class="mb-3">Estado: <strong>{{ $cierre->estadoTexto() }}</strong></p>
                @endif
                @if (! $cierre && $anteriores->isNotEmpty())
                    <div class="tc-cierre-abierta">La caja está abierta. El cierre de arriba quedó en la historia.</div>
                @endif
                @if (! $cierre && ! $calculo['puede'])
                    <div class="alert alert-warning">{{ $calculo['motivo'] }}</div>
                @elseif (! $cierre && $calculo['supera'])
                    <div class="alert alert-warning">La diferencia supera la tolerancia de {{ $pesos($calculo['tolerancia']) }}. El cierre va a quedar en auditoría.</div>
                @endif

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0" style="max-width:42rem;">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Concepto</th><th class="text-right" style="width:10rem;">Pesos</th></tr>
                        </thead>
                        <tbody>
                            @if (is_array($detalle))
                                @foreach ($detalle as $filaDetalle)
                                    <tr>
                                        <td>
                                            {{ $filaDetalle['concepto'] }}
                                            @if (empty($filaDetalle['entra']))
                                                <span class="text-muted">· no entra en la diferencia</span>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ $pesos($filaDetalle['importe'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td>Efectivo habilitado</td><td class="text-right">{{ $pesos($habilitado) }}</td></tr>
                                <tr><td>Efectivo rendido</td><td class="text-right">{{ $pesos($rendido) }}</td></tr>
                                <tr><td>Resultado</td><td class="text-right">{{ $pesos($resultado) }}</td></tr>
                            @endif
                            <tr>
                                <td><strong>Diferencia · {{ $signo }}</strong></td>
                                <td class="text-right"><strong>{{ $pesos($diferencia) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted">La cuenta es la de Anita. El efectivo es pesos, moneda extranjera por la cotización de la jornada, crédito menos el recargo, débito y QR. Las fichas van aparte. En venta, kiosco y cashless se suma lo rendido y se resta lo habilitado. En gastronomía y hotel se resta el X menos las notas de crédito y el fallo de mozo, y se suma la transferencia con la cuenta corriente. En empleados la diferencia queda en cero. Un número negativo es faltante.</p>

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
                    <form method="POST" action="{{ route('cerrar_tesoreria_crown_cierre', $asignacion->id) }}" class="form-horizontal form--label-right" data-enter-campos="1">
                        @csrf
                        @include('includes.form-error')
                        @if ($calculo['supera'])
                            <div class="form-group row">
                                <label class="col-lg-4 control-label text-right pr-2">Resultado del cierre</label>
                                <div class="col-lg-8">
                                    <p class="form-control-plaintext mb-0">Auditoría. Conforme y a revisión no se eligen cuando la diferencia supera la tolerancia.</p>
                                </div>
                            </div>
                        @else
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
                        @endif
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
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Cerrar caja</button>
                    </form>
                @endif

                @if ($puedeMover && can('editar-tesoreria-crown-movimiento', false))
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('revisar_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3" data-enter-campos="1">
                                @csrf
                                <h4 class="h6">Marcar revisada</h4>
                                <input type="text" name="observacion" class="form-control form-control-sm mb-2" maxlength="500" placeholder="Observación">
                                <input type="text" name="firma_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario de tesorería" autocomplete="username" required>
                                <input type="password" name="firma_password" class="form-control form-control-sm mb-2" placeholder="Clave" autocomplete="current-password" required>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Revisada</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('auditar_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3" data-enter-campos="1">
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

                @if ($cierre && can(\App\Support\Caja\TesoreriaCrownCierreSupport::PERMISO_REABRIR, false))
                    <form method="POST" action="{{ route('reabrir_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3 form-horizontal form--label-right" style="max-width:40rem;" data-enter-campos="1">
                        @csrf
                        <h4 class="h6">Reabrir caja</h4>
                        <p class="text-muted">El cierre no se borra. La caja vuelve a operativa para revertir o cargar otro comprobante y cerrarla de nuevo. Si el día estaba cerrado y no hay otra jornada abierta, el día también vuelve a abierto. Lo autoriza un usuario con el permiso de reabrir, con su clave.</p>
                        <div class="form-group row">
                            <label for="motivo" class="col-lg-4 control-label text-right pr-2">Motivo</label>
                            <div class="col-lg-8">
                                <input type="text" name="motivo" id="motivo" class="form-control form-control-sm" maxlength="500" value="{{ old('motivo') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="reabrir_usuario" class="col-lg-4 control-label text-right pr-2">Autoriza</label>
                            <div class="col-lg-8">
                                <input type="text" name="firma_usuario" id="reabrir_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario" value="{{ old('firma_usuario') }}" autocomplete="username" required>
                                <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="current-password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Reabrir esta caja? El cierre queda en la historia.');">Reabrir caja</button>
                    </form>
                @endif

                @if ($estado === \App\Support\Caja\TesoreriaCrownCierreSupport::AUDITORIA && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('firmar_auditoria_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3" style="max-width:36rem;" data-enter-campos="1">
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
