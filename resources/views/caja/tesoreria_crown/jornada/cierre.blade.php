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
    $filasVivas = $calculo['componentes']['filas'] ?? [];
    $piezas = $calculo['piezas'] ?? [];
    $deClave = function (string $clave) use ($piezas): array {
        return array_values(array_filter($piezas, function (array $pieza) use ($clave) {
            return abs((float) ($pieza['aportes'][$clave] ?? 0)) > 0.001;
        }));
    };
    $ajustes = $ajustes ?? collect();
    $numerosVigentes = ($ajustes->isNotEmpty() && is_array($vigente ?? null)) ? $vigente : [
        'habilitado' => $calculo['habilitado'],
        'rendido' => $calculo['rendido'],
        'resultado' => $calculo['resultado'],
        'diferencia' => $calculo['diferencia'],
        'signo' => $calculo['signo'] ?? '',
    ];
    $tonoDiferencia = match ($numerosVigentes['signo'] ?? '') {
        'Faltante' => 'is-faltante',
        'Excedente' => 'is-excedente',
        default => 'is-cero',
    };
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
                    — {{ $asignacion->cajero->codigo ?? '' }} {{ $asignacion->cajero->nombre ?? '' }}
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

                <div class="tc-cierre-trabajo">
                    <div class="tc-cierre-lista">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 tc-cierre-tabla">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Concepto</th>
                                        <th class="text-right">Habilitación</th>
                                        <th class="text-right">Rendición</th>
                                        <th class="tc-cierre-accion"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($filasVivas as $filaDetalle)
                                        @php
                                            $claveFila = (string) ($filaDetalle['clave'] ?? '');
                                            $docsFila = $claveFila !== '' ? $deClave($claveFila) : [];
                                            $numeroFila = count($docsFila) === 1 ? $docsFila[0]['numero'] : '';
                                            $columnaFila = (string) ($filaDetalle['columna'] ?? '');
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $filaDetalle['concepto'] }}
                                                @if ($numeroFila !== '')
                                                    <span class="tc-cierre-nro">{{ $numeroFila }}</span>
                                                @endif
                                                @if (empty($filaDetalle['entra']))
                                                    <span class="text-muted">· no entra en la diferencia</span>
                                                @endif
                                            </td>
                                            <td class="text-right">{{ $columnaFila === 'habilitacion' ? $pesos($filaDetalle['importe'] ?? 0) : '' }}</td>
                                            <td class="text-right">{{ $columnaFila === 'rendicion' ? $pesos($filaDetalle['importe'] ?? 0) : '' }}</td>
                                            <td class="tc-cierre-accion">
                                                @if ($claveFila !== '' && $docsFila !== [])
                                                    <button type="button" class="tc-cierre-ver" data-clave="{{ $claveFila }}">Ver</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>Totales</th>
                                        <th class="text-right">{{ $pesos($calculo['habilitado']) }}</th>
                                        <th class="text-right">{{ $pesos($calculo['rendido']) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="tc-cierre-resumen {{ $tonoDiferencia }}">
                            @foreach ([
                                ['clave' => 'efectivo_habilitado', 'concepto' => 'Efectivo habilitado', 'importe' => $numerosVigentes['habilitado']],
                                ['clave' => 'resultado', 'concepto' => 'Resultado de caja', 'importe' => $numerosVigentes['resultado']],
                                ['clave' => 'valores_rendidos', 'concepto' => 'Valores rendidos', 'importe' => $numerosVigentes['rendido']],
                            ] as $lineaResumen)
                                <div class="tc-cierre-resumen-fila">
                                    <span>{{ $lineaResumen['concepto'] }}</span>
                                    <strong>{{ $pesos($lineaResumen['importe']) }}</strong>
                                    <button type="button" class="tc-cierre-ver" data-clave="{{ $lineaResumen['clave'] }}">Ver</button>
                                </div>
                            @endforeach
                            <div class="tc-cierre-resumen-fila is-diferencia">
                                <span>Diferencia · {{ $numerosVigentes['signo'] }}</span>
                                <strong>{{ $pesos($numerosVigentes['diferencia']) }}</strong>
                                <button type="button" class="tc-cierre-ver" data-clave="diferencia">Ver</button>
                            </div>
                            <p class="tc-cierre-ecuacion">Diferencia = valores rendidos − efectivo habilitado − resultado de caja.</p>
                            @if ($ajustes->isNotEmpty())
                                <p class="text-muted mb-0">Cierre vigente: originales más ajustes. Ver abre los comprobantes originales.</p>
                            @endif
                        </div>
                    </div>

                    <aside class="tc-cierre-panel" id="tc-cierre-panel">
                        <h4 class="tc-cierre-panel-titulo" id="tc-cierre-panel-titulo">Comprobantes</h4>
                        <p class="tc-cierre-panel-vacio" id="tc-cierre-panel-vacio">Elegí Ver para listar los movimientos de ese importe. Desde ahí se abre el comprobante y, si la caja sigue abierta, se puede revertir o modificar.</p>
                        <div class="tc-cierre-panel-cuerpo" id="tc-cierre-panel-diferencia" hidden>
                            <p class="tc-cierre-ecuacion">{{ $pesos($calculo['rendido']) }} − {{ $pesos($calculo['habilitado']) }} − {{ $pesos($calculo['resultado']) }} = {{ $pesos($calculo['diferencia']) }}</p>
                            <p>Un positivo es excedente. Un negativo es faltante. Cero es sin diferencia.</p>
                        </div>
                        @php
                            $urlComprobante = function ($id) {
                                return route('editar_tesoreria_crown_movimiento', [
                                    'id' => $id,
                                    'origen' => 'modal_consulta',
                                    'vista' => 'consulta',
                                ]);
                            };
                        @endphp
                        @foreach (['habilitacion', 'valores', 'recargo', 'fichas', 'cashless', 'voucher', 'online', 'apuestas', 'arendir', 'hotel', 'ventaz', 'efectivo_habilitado', 'resultado', 'valores_rendidos'] as $clavePanel)
                            @php $docsPanel = $clavePanel === 'resultado' ? $deClave('arendir') : $deClave($clavePanel); @endphp
                            <div class="tc-cierre-panel-cuerpo" data-clave="{{ $clavePanel }}" hidden>
                                @forelse ($docsPanel as $docPanel)
                                    <article class="tc-cierre-doc">
                                        <header>
                                            <a class="text-primary" target="_blank" rel="noopener" href="{{ $urlComprobante($docPanel['id']) }}">{{ $docPanel['tipo'] }} {{ $docPanel['numero'] }}</a>
                                            <span>{{ $pesos($docPanel['aportes'][$clavePanel === 'resultado' ? 'arendir' : $clavePanel] ?? 0) }}</span>
                                        </header>
                                        <p class="tc-cierre-doc-estado">{{ $docPanel['estado'] }}</p>
                                        <ul>
                                            @foreach ($docPanel['renglones'][$clavePanel === 'resultado' ? 'arendir' : $clavePanel] ?? [] as $renglon)
                                                @if (abs((float) $renglon['importe']) > 0.001)
                                                    <li>
                                                        <span>{{ $renglon['etiqueta'] }}</span>
                                                        <span>{{ $pesos($renglon['importe']) }}</span>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                        <div class="tc-cierre-doc-acciones">
                                            <a class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" href="{{ $urlComprobante($docPanel['id']) }}" title="{{ ($docPanel['puede_modificar'] ?? false) ? 'Modificar' : 'Abrir' }}">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            @if ($docPanel['puede_volver'] && can('crear-tesoreria-crown-movimiento', false))
                                                <form method="POST" target="_blank" action="{{ route('volver_borrador_tesoreria_crown_movimiento', ['id' => $docPanel['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="d-inline mb-0" onsubmit="return confirm('¿Volver este comprobante a borrador para modificarlo?');">
                                                    @csrf
                                                    <input type="hidden" name="origen" value="modal_consulta">
                                                    <input type="hidden" name="vista" value="consulta">
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Modificar">
                                                        <i class="fa fa-edit"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($docPanel['puede_revertir'] && can('editar-tesoreria-crown-movimiento', false))
                                                <form method="POST" target="_blank" action="{{ route('revertir_tesoreria_crown_movimiento', ['id' => $docPanel['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}" class="d-inline mb-0" onsubmit="return confirm('¿Revertir este comprobante? La reversa queda pendiente de las dos firmas.');">
                                                    @csrf
                                                    <input type="hidden" name="origen" value="modal_consulta">
                                                    <input type="hidden" name="vista" value="consulta">
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Revertir">
                                                        <i class="fa fa-undo text-warning"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($docPanel['reversa_id'])
                                                <a class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" href="{{ $urlComprobante($docPanel['reversa_id']) }}" title="Reversa {{ $docPanel['reversa_numero'] }}">
                                                    <i class="fa fa-undo"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </article>
                                @empty
                                    <p class="text-muted mb-0">Ningún comprobante conforme aporta a este importe.</p>
                                @endforelse
                            </div>
                        @endforeach
                    </aside>
                </div>
                <p class="text-muted">El detalle sigue el listado de Anita. Abajo, el resumen: efectivo habilitado, resultado de caja, valores rendidos y la diferencia. Ver abre los comprobantes de ese importe.</p>

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
                                <input type="text" name="clave_cajero" id="clave_cajero" class="form-control tc-pin-cajero" placeholder="Clave del cajero" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" required style="max-width:16rem;">
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

                @if ($ajustes->isNotEmpty())
                    <h4 class="h6 mt-3">Ajustes</h4>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Número</th>
                                    <th>Corrige</th>
                                    <th class="text-right">Importe</th>
                                    <th>Motivo</th>
                                    <th>Audita</th>
                                    <th>Tesorería</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ajustes as $ajuste)
                                    <tr>
                                        <td>{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($ajuste->numero) }}</td>
                                        <td>{{ $ajuste->concepto() }}</td>
                                        <td class="text-right">{{ $pesos($ajuste->importe) }}</td>
                                        <td>{{ $ajuste->motivo }}</td>
                                        <td>{{ $ajuste->auditor->nombre ?? '' }}</td>
                                        <td>{{ $ajuste->tesoreria->nombre ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($cierre && can(\App\Support\Caja\TesoreriaCrownCierreSupport::PERMISO_AJUSTAR, false))
                    <form method="POST" action="{{ route('ajustar_tesoreria_crown_cierre', $asignacion->id) }}" class="border rounded p-3 mb-3 form-horizontal form--label-right" style="max-width:44rem;" data-enter-campos="1">
                        @csrf
                        <h4 class="h6">Ajuste de caja</h4>
                        <p class="text-muted">Comprobante nuevo sobre este cierre. No reescribe la habilitación, la rendición ni los números originales, y no reabre la caja ni el día. Un importe positivo suma al concepto; uno negativo lo resta. Si la diferencia vigente supera la tolerancia, el caso vuelve a auditoría.</p>
                        <div class="form-group row">
                            <label for="ajuste_corrige" class="col-lg-4 control-label text-right pr-2">Corrige</label>
                            <div class="col-lg-8">
                                <select name="corrige" id="ajuste_corrige" class="form-control form-control-sm" required>
                                    @foreach (\App\Models\Caja\TesoreriaCrownAjuste::CORRIGE as $codigoCorrige => $etiquetaCorrige)
                                        <option value="{{ $codigoCorrige }}" @selected(old('corrige') === $codigoCorrige)>{{ $etiquetaCorrige }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="ajuste_importe" class="col-lg-4 control-label text-right pr-2">Importe</label>
                            <div class="col-lg-8">
                                <input type="number" name="importe" id="ajuste_importe" class="form-control form-control-sm" step="0.01" value="{{ old('importe') }}" required style="max-width:12rem;">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="ajuste_motivo" class="col-lg-4 control-label text-right pr-2">Motivo</label>
                            <div class="col-lg-8">
                                <input type="text" name="motivo" id="ajuste_motivo" class="form-control form-control-sm" maxlength="500" value="{{ old('motivo') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="auditor_usuario" class="col-lg-4 control-label text-right pr-2">Audita</label>
                            <div class="col-lg-8">
                                <input type="text" name="auditor_usuario" id="auditor_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario" value="{{ old('auditor_usuario') }}" autocomplete="username" required>
                                <input type="password" name="auditor_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="current-password" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="tesoreria_usuario" class="col-lg-4 control-label text-right pr-2">Tesorería</label>
                            <div class="col-lg-8">
                                <input type="text" name="tesoreria_usuario" id="tesoreria_usuario" class="form-control form-control-sm mb-2" placeholder="Usuario" value="{{ old('tesoreria_usuario') }}" autocomplete="username" required>
                                <input type="password" name="tesoreria_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="current-password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Grabar ajuste</button>
                    </form>
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
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Reabrir esta caja? El cierre queda en la historia.');"><i class="fa fa-unlock-alt"></i> Reabrir caja</button>
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

@section('scripts')
<script>
(function () {
    var panel = document.getElementById('tc-cierre-panel');
    if (!panel) {
        return;
    }
    var titulo = document.getElementById('tc-cierre-panel-titulo');
    var vacio = document.getElementById('tc-cierre-panel-vacio');
    var cuerpos = panel.querySelectorAll('.tc-cierre-panel-cuerpo');
    document.querySelectorAll('.tc-cierre-ver').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var clave = boton.getAttribute('data-clave');
            document.querySelectorAll('.tc-cierre-ver.is-on').forEach(function (otro) {
                otro.classList.remove('is-on');
            });
            boton.classList.add('is-on');
            if (vacio) {
                vacio.hidden = true;
            }
            cuerpos.forEach(function (cuerpo) {
                cuerpo.hidden = cuerpo.getAttribute('data-clave') !== clave && cuerpo.id !== 'tc-cierre-panel-' + clave;
            });
            var fila = boton.closest('tr, .tc-cierre-resumen-fila');
            var nombre = fila ? fila.querySelector('td, span') : null;
            if (titulo && nombre) {
                titulo.textContent = nombre.textContent.trim();
            }
            panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    });
})();
</script>
@endsection
