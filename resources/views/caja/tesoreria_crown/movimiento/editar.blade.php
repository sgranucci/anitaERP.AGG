@extends("theme.$theme.layout")

@section('titulo')
    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
@endsection

@section('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/tesoreria-crown-jornada.css') }}?v={{ filemtime(public_path('assets/css/tesoreria-crown-jornada.css')) }}">
@endsection

@section('scripts')
<script>
(function () {
    function texto(numero) {
        return numero.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function importePanel(panel) {
        var inputs = panel.querySelectorAll('input[data-denom]');
        if (!inputs.length) {
            return null;
        }
        var total = 0;
        inputs.forEach(function (input) {
            var cantidad = parseFloat(String(input.value).replace(',', '.'));
            var denominacion = parseFloat(input.getAttribute('data-denom'));
            if (!isNaN(cantidad) && !isNaN(denominacion)) {
                total += cantidad * denominacion;
            }
        });
        return total;
    }
    function actualizarTotalPesos() {
        var total = 0;
        var hayCantidades = false;
        document.querySelectorAll('.js-panel-valor[data-en-pesos="1"]').forEach(function (panel) {
            var subtotal = importePanel(panel);
            if (subtotal === null) {
                return;
            }
            hayCantidades = true;
            var tasa = parseFloat(panel.getAttribute('data-tasa'));
            var codigo = panel.getAttribute('data-grupo');
            var pesos = tasa > 0 ? subtotal * tasa : 0;
            if (tasa > 0) {
                total += pesos;
            }
            if (!codigo) {
                return;
            }
            document.querySelectorAll('.js-pesos-origen[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = texto(subtotal);
            });
            document.querySelectorAll('.js-pesos-linea[data-grupo="' + codigo + '"], .js-total-en-pesos[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = texto(pesos);
            });
        });
        if (hayCantidades) {
            document.querySelectorAll('.js-total-pesos').forEach(function (nodo) {
                nodo.textContent = texto(total);
            });
        }
        var medios = sumaMedios();
        if (!hayCantidades && !medios.hay) {
            return;
        }
        var efectivo = hayCantidades ? total : NaN;
        document.querySelectorAll('.js-total-final').forEach(function (nodo) {
            var base = hayCantidades ? efectivo : parseFloat(nodo.getAttribute('data-efectivo'));
            if (isNaN(base)) {
                return;
            }
            nodo.textContent = texto(base + medios.extra);
        });
    }
    function sumaMedios() {
        var extra = 0;
        var hay = false;
        document.querySelectorAll('input[data-total-final]').forEach(function (input) {
            hay = true;
            var signo = parseFloat(input.getAttribute('data-total-final'));
            var valor = parseFloat(String(input.value).replace(',', '.'));
            if (!isNaN(signo) && !isNaN(valor)) {
                extra += valor * signo;
            }
        });
        return { extra: extra, hay: hay };
    }
    function recalcular(panel) {
        var total = importePanel(panel);
        if (total === null) {
            return;
        }
        var escrito = texto(total);
        panel.querySelectorAll('.js-total-valor').forEach(function (nodo) {
            nodo.textContent = escrito;
        });
        var codigo = panel.getAttribute('data-grupo');
        if (codigo) {
            document.querySelectorAll('.js-total-barra[data-grupo="' + codigo + '"]').forEach(function (nodo) {
                nodo.textContent = escrito;
            });
        }
        actualizarTotalPesos();
    }
    document.querySelectorAll('.js-panel-valor').forEach(function (panel) {
        recalcular(panel);
        panel.addEventListener('input', function () { recalcular(panel); });
    });
    document.querySelectorAll('input[data-total-final]').forEach(function (input) {
        input.addEventListener('input', actualizarTotalPesos);
    });
    var credito = document.getElementById('medio-tarjeta_credito');
    var recargo = document.getElementById('medio-recargo_fin');
    if (credito && recargo && recargo.hasAttribute('data-recargo-divisor')) {
        var ponerRecargo = function () {
            var importe = parseFloat(String(credito.value).replace(',', '.'));
            var divisor = parseFloat(recargo.getAttribute('data-recargo-divisor'));
            if (isNaN(importe) || isNaN(divisor) || divisor === 0) {
                recargo.value = '';
            } else {
                recargo.value = (Math.round((importe - importe / divisor) * 100) / 100).toFixed(2);
            }
            actualizarTotalPesos();
        };
        credito.addEventListener('input', ponerRecargo);
        ponerRecargo();
    }

    var primero = document.querySelector('#form-cantidades-crown input[data-denom], #form-cantidades-crown input.js-importe');
    if (primero) {
        primero.focus();
        primero.select();
    }
})();
</script>
@endsection

@section('contenido')
@include('includes.mensaje')
@if (! empty($cajaCerrada))
    <div class="alert alert-info">Esta caja ya está cerrada. El comprobante queda en consulta.</div>
@endif
@if (($comprobante->asignacion->cajero ?? null) && \App\Support\Caja\TesoreriaCrownFirmaSupport::pinEsInicial($comprobante->asignacion->cajero) && empty($cajaCerrada) && $comprobante->jornada->estaAbierta())
    <div class="alert alert-warning">
        <strong>PIN del cajero.</strong>
        {{ $comprobante->asignacion->cajero->nombre }} todavía no tiene un PIN propio (usa el inicial o está blanqueado).
        En la próxima firma el sistema le va a pedir que elija uno nuevo delante de tesorería.
        Si se olvidó el PIN, en Pendiente usá «El cajero se olvidó el PIN».
    </div>
@endif
@php
    $esHabilitacion = $comprobante->tipo === \App\Support\Caja\TesoreriaCrownComprobanteSupport::HABILITACION;
    $etiquetaEntrega = \App\Support\Caja\TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'entrega');
    $etiquetaRecibe = \App\Support\Caja\TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'recibe');
    $ladoCajero = \App\Support\Caja\TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo);
    $ladoTesoreria = \App\Support\Caja\TesoreriaCrownFirmaSupport::ladoTesoreria($comprobante->tipo);
    $cajeroFirma = $comprobante->asignacion->cajero;
    $reversa = $comprobante->reversa;
    $yaRevertido = $reversa !== null;
    $fechaJornada = $comprobante->jornada->fecha->format('Y-m-d');
    $efectivoPesos = $esHabilitacion
        ? null
        : \App\Support\Caja\TesoreriaCrownCierreCalculo::efectivoEnPesos(
            (int) $comprobante->sala_id,
            $fechaJornada,
            $grupos,
            $cantidades,
            $comprobante,
        );
    $monedasPesos = [];
    foreach ($efectivoPesos['monedas'] ?? [] as $monedaPesos) {
        $monedasPesos[$monedaPesos['codigo']] = $monedaPesos;
    }
    $cambiosDia = \App\Support\Caja\TesoreriaCrownCierreCalculo::cambiosParaComprobante($comprobante);
    $totalFinal = $efectivoPesos
        ? \App\Support\Caja\TesoreriaCrownCierreCalculo::totalFinal(
            (float) $efectivoPesos['total'],
            (float) $comprobante->tarjeta_credito,
            (float) $comprobante->tarjeta_debito,
            (float) $comprobante->recargo_fin,
            (float) $comprobante->total_qr,
        )
        : null;
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    {{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }}
                    {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($comprobante->numero) }}
                    · {{ $comprobante->jornada->fecha->format('d/m/Y') }}
                    — {{ $comprobante->asignacion->deposito->nombre ?? '' }}
                    — {{ $comprobante->asignacion->cajero->nombre ?? '' }}
                </h3>
                <div class="card-tools d-flex align-items-center" style="gap:.35rem;">
                    @if ($cambiosDia !== [])
                        <span class="small mr-1" style="opacity:.85;white-space:nowrap;" title="{{ collect($cambiosDia)->contains(fn ($c) => ! empty($c['del_comprobante'])) ? 'Cotización sellada en el comprobante' : 'Cotización de venta de la jornada' }}">
                            @foreach ($cambiosDia as $cambio)
                                @if (! $loop->first)
                                    ·
                                @endif
                                {{ $cambio['codigo'] }}
                                @if ($cambio['tasa'] > 0)
                                    @php
                                        $decimalesCambio = abs($cambio['tasa'] - round($cambio['tasa'], 2)) < 0.0000001 ? 2 : 4;
                                    @endphp
                                    {{ number_format($cambio['tasa'], $decimalesCambio, ',', '.') }}
                                    @if (! empty($cambio['del_comprobante']))
                                        <span class="text-muted">(comp.)</span>
                                    @elseif (! $cambio['exacta'] && $cambio['fecha'])
                                        ({{ \Carbon\Carbon::parse($cambio['fecha'])->format('d/m') }})
                                    @endif
                                @else
                                    sin cambio
                                @endif
                            @endforeach
                        </span>
                    @endif
                    <a href="{{ route('pdf_tesoreria_crown_movimiento', $comprobante->id) }}" class="btn btn-light btn-sm" target="_blank" rel="noopener">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    @if ($comprobante->estado === 'C' && $comprobante->revierte_id === null && ! $yaRevertido && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                        <a href="#tc-revertir" class="btn btn-outline-light btn-sm" onclick="document.getElementById('tc-revertir').open = true;"><i class="fa fa-undo"></i> Revertir</a>
                    @endif
                    @if (request()->input('vista') === 'consulta')
                        <button type="button" class="btn btn-outline-light btn-sm" onclick="window.close()">Cerrar solapa</button>
                    @else
                        <a href="{{ route('ver_tesoreria_crown_jornada', $comprobante->jornada_id) }}" class="btn btn-outline-light btn-sm">
                            <i class="fa fa-reply-all"></i> Volver a la jornada
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Estado: <strong>{{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($comprobante->estado) }}</strong>
                </p>
                @if ($reversa)
                    <div class="tc-cierre-anterior mb-3">
                        <div class="tc-papel is-revertido">
                            <span class="tc-papel-marca is-revertido"><i class="fa fa-undo"></i> Reversada</span>
                            <a class="tc-papel-nro" href="{{ route('editar_tesoreria_crown_movimiento', $reversa->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($reversa->numero) }}</a>
                            <span class="tc-papel-estado">{{ \App\Support\Caja\TesoreriaCrownComprobanteSupport::estado($reversa->estado) }}</span>
                        </div>
                        <p class="tc-cierre-meta">{{ collect([$reversa->creadoUsuario ? 'Por '.$reversa->creadoUsuario->nombre : null, $reversa->created_at?->format('d/m/Y H:i')])->filter()->implode(' · ') }}</p>
                    </div>
                @endif
                @if ($comprobante->revierte)
                    <div class="tc-cierre-anterior mb-3">
                        <div class="tc-papel is-reversa">
                            <span class="tc-papel-marca is-reversa"><i class="fa fa-undo"></i> Reversa de</span>
                            <a class="tc-papel-nro text-primary" href="{{ route('editar_tesoreria_crown_movimiento', $comprobante->revierte->id) }}">{{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($comprobante->revierte->numero) }}</a>
                        </div>
                        <p class="tc-cierre-meta">{{ collect([$comprobante->creadoUsuario ? 'Por '.$comprobante->creadoUsuario->nombre : null, $comprobante->created_at?->format('d/m/Y H:i')])->filter()->implode(' · ') }}</p>
                    </div>
                @endif
                @if ($comprobante->observacion)
                    <p class="text-muted" style="white-space:pre-line;">{{ $comprobante->observacion }}</p>
                @endif

                @if ($esHabilitacion)
                    <p class="text-muted">Efectivo de la habilitación: pesos, dólares y euros, de mayor denominación a menor.</p>
                @else
                    <p class="text-muted">Efectivo, fichas y, al final, cashless y tarjetas.@if (\App\Support\Caja\TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->asignacion->tipo_tarea ?? '')) Esta caja se abrió como {{ \App\Support\Caja\TesoreriaCrownAsignacionCatalogo::tarea($comprobante->asignacion->tipo_tarea) }}, así que también pide el informe de gastronomía u hotel.@endif</p>
                @endif

                <form method="POST" action="{{ route('guardar_tesoreria_crown_movimiento', $comprobante->id) }}" id="form-cantidades-crown" data-enter-campos="1">
                    @csrf
                    @if ($editable)
                        <div class="border rounded px-3 py-2 mb-3 bg-light d-flex flex-wrap align-items-center" id="barra-totales-crown" style="position:sticky;top:3.5rem;z-index:5;">
                            @foreach ($grupos as $grupoBarra)
                                @php
                                    $totalBarra = 0.0;
                                    foreach ($grupoBarra['filas'] as $filaBarra) {
                                        $totalBarra += (float) $filaBarra->denominacion * (float) ($cantidades[$filaBarra->id] ?? 0);
                                    }
                                @endphp
                                <span class="mr-4 mb-1">
                                    <span class="text-muted">{{ $grupoBarra['titulo'] }}</span>
                                    <strong class="js-total-barra" data-grupo="{{ $grupoBarra['codigo'] }}">{{ number_format($totalBarra, 2, ',', '.') }}</strong>
                                </span>
                            @endforeach
                            @if ($efectivoPesos)
                                <span class="ml-auto mb-1">
                                    <span class="text-muted">Total final $</span>
                                    <strong class="js-total-final" data-efectivo="{{ number_format((float) $efectivoPesos['total'], 2, '.', '') }}">{{ number_format($totalFinal, 2, ',', '.') }}</strong>
                                </span>
                            @endif
                        </div>
                    @endif
                    @php
                        $bloquesValor = [];
                        foreach ($grupos as $grupoBloque) {
                            $claveBloque = ! empty($grupoBloque['colapsado']) ? 'fichas' : 'billetes';
                            $bloquesValor[$claveBloque]['titulo'] = $claveBloque === 'fichas' ? 'Fichas' : 'Billetes';
                            $bloquesValor[$claveBloque]['abierto'] = $claveBloque !== 'fichas';
                            $bloquesValor[$claveBloque]['grupos'][] = $grupoBloque;
                        }
                    @endphp
                    @foreach ($bloquesValor as $claveBloque => $bloqueValor)
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2 d-flex align-items-center tc-bloque-toggle{{ $bloqueValor['abierto'] ? '' : ' collapsed' }}" data-toggle="collapse" data-target="#bloque-valor-{{ $claveBloque }}" role="button" aria-expanded="{{ $bloqueValor['abierto'] ? 'true' : 'false' }}" title="{{ $bloqueValor['abierto'] ? 'Ocultar' : 'Mostrar' }}">
                                <i class="fa fa-chevron-down mr-2 tc-bloque-chevron"></i>
                                <h3 class="card-title mb-0">{{ $bloqueValor['titulo'] }}</h3>
                            </div>
                            <div id="bloque-valor-{{ $claveBloque }}" class="collapse{{ $bloqueValor['abierto'] ? ' show' : '' }}">
                                <div class="card-body pb-2">
                                    <div class="row">
                        @foreach ($bloqueValor['grupos'] as $grupo)
                            @php
                                $totalGrupo = 0.0;
                                foreach ($grupo['filas'] as $filaTotal) {
                                    $totalGrupo += (float) $filaTotal->denominacion * (float) ($cantidades[$filaTotal->id] ?? 0);
                                }
                                $monedaPesos = $monedasPesos[$grupo['codigo']] ?? null;
                                $tasaTexto = '';
                                if ($monedaPesos && $monedaPesos['extranjera'] && ! $monedaPesos['falta']) {
                                    $tasaNumero = (float) $monedaPesos['tasa'];
                                    $decimalesTasa = abs($tasaNumero - round($tasaNumero, 2)) < 0.0000001 ? 2 : 4;
                                    $tasaTexto = number_format($tasaNumero, $decimalesTasa, ',', '.');
                                }
                            @endphp
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="card card-outline card-info h-100 mb-0 js-panel-valor" data-grupo="{{ $grupo['codigo'] }}" data-en-pesos="{{ $monedaPesos ? '1' : '0' }}" data-tasa="{{ $monedaPesos ? number_format((float) $monedaPesos['tasa'], 6, '.', '') : '0' }}">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                        <h3 class="card-title mb-0">{{ $grupo['titulo'] }}</h3>
                                        <strong class="js-total-valor">{{ number_format($totalGrupo, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="card-body p-0 table-responsive">
                                        <table class="table table-sm table-bordered mb-0">
                                            <thead style="background:#85C1E9;color:#17202A;">
                                                <tr>
                                                    <th>Denominación</th>
                                                    <th>Tipo</th>
                                                    <th style="width:110px;">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($grupo['filas'] as $valor)
                                                    @php
                                                        $denominacion = (float) $valor->denominacion;
                                                        $denominacionTexto = fmod($denominacion, 1.0) === 0.0
                                                            ? number_format($denominacion, 0, ',', '.')
                                                            : number_format($denominacion, 2, ',', '.');
                                                    @endphp
                                                    <tr>
                                                        <td class="text-right">{{ $denominacionTexto }}</td>
                                                        <td>{{ $valor->descripcion }}</td>
                                                        <td>
                                                            @if ($editable)
                                                                <input type="number" name="cantidad[{{ $valor->id }}]" class="form-control form-control-sm text-right" min="0" step="1" inputmode="numeric" data-denom="{{ $denominacion }}" value="{{ old('cantidad.'.$valor->id, isset($cantidades[$valor->id]) && $cantidades[$valor->id] !== '' ? (int) $cantidades[$valor->id] : '') }}">
                                                            @elseif (isset($cantidades[$valor->id]) && (int) $cantidades[$valor->id] !== 0)
                                                                {{ number_format((int) $cantidades[$valor->id], 0, ',', '.') }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="card-footer py-2 text-right">
                                        Total <strong class="js-total-valor">{{ number_format($totalGrupo, 2, ',', '.') }}</strong>
                                        @if ($monedaPesos && $monedaPesos['extranjera'])
                                            <div class="text-muted small mt-1">
                                                @if ($monedaPesos['falta'])
                                                    Sin cotización de venta para el {{ $comprobante->jornada->fecha->format('d/m/Y') }}
                                                @else
                                                    Cambio {{ $tasaTexto }}
                                                    @if ($monedaPesos['fecha'] === $fechaJornada)
                                                        del día
                                                    @else
                                                        del {{ \Carbon\Carbon::parse($monedaPesos['fecha'])->format('d/m/Y') }}
                                                    @endif
                                                    · En pesos <strong class="js-total-en-pesos" data-grupo="{{ $grupo['codigo'] }}">{{ number_format($monedaPesos['pesos'], 2, ',', '.') }}</strong>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if ($efectivoPesos)
                        <div class="card card-outline card-primary mb-3">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <h3 class="card-title mb-0">Total en pesos</h3>
                                <strong class="js-total-pesos">{{ number_format($efectivoPesos['total'], 2, ',', '.') }}</strong>
                            </div>
                            <div class="card-body py-2">
                                <p class="text-muted small mb-2">Pesos al nominal. Dólares y euros al cambio de venta de la jornada {{ $comprobante->jornada->fecha->format('d/m/Y') }}. Las fichas no entran en este total.</p>
                                @foreach ($efectivoPesos['monedas'] as $monedaLinea)
                                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                                        <span>
                                            {{ $monedaLinea['titulo'] }}
                                            @if ($monedaLinea['extranjera'] && ! $monedaLinea['falta'])
                                                @php
                                                    $tasaLinea = (float) $monedaLinea['tasa'];
                                                    $decimalesLinea = abs($tasaLinea - round($tasaLinea, 2)) < 0.0000001 ? 2 : 4;
                                                @endphp
                                                <span class="text-muted small">
                                                    <span class="js-pesos-origen" data-grupo="{{ $monedaLinea['codigo'] }}">{{ number_format($monedaLinea['origen'], 2, ',', '.') }}</span>
                                                    × {{ number_format($tasaLinea, $decimalesLinea, ',', '.') }}
                                                    @if ($monedaLinea['fecha'] !== $fechaJornada)
                                                        ({{ \Carbon\Carbon::parse($monedaLinea['fecha'])->format('d/m/Y') }})
                                                    @endif
                                                </span>
                                            @elseif ($monedaLinea['falta'])
                                                <span class="text-danger small">Sin cotización de venta</span>
                                            @endif
                                        </span>
                                        <strong class="js-pesos-linea" data-grupo="{{ $monedaLinea['codigo'] }}">{{ number_format($monedaLinea['pesos'], 2, ',', '.') }}</strong>
                                    </div>
                                @endforeach
                                @if (! $efectivoPesos['completo'])
                                    <p class="text-danger small mb-0 mt-2">Falta la cotización de una moneda extranjera. Esa moneda no entra en el total.</p>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if (! $esHabilitacion)
                        @include('caja.tesoreria_crown.movimiento.partials.medios_rendicion')
                    @endif
                    @if ($efectivoPesos)
                        <div class="border rounded px-3 py-2 mb-3 d-flex flex-wrap justify-content-between align-items-center" style="background:#EAF2F8;">
                            <div class="mr-3">
                                <strong>Total final $</strong>
                                <div class="text-muted small">
                                    @if ($esHabilitacion)
                                        Pesos más dólares y euros al cambio de la jornada. Las fichas no entran.
                                    @else
                                        Pesos más dólares y euros al cambio, más crédito y débito, menos el recargo, más el QR. Las fichas no entran.
                                    @endif
                                </div>
                            </div>
                            <strong class="js-total-final h5 mb-0" data-efectivo="{{ number_format((float) $efectivoPesos['total'], 2, '.', '') }}">{{ number_format($totalFinal, 2, ',', '.') }}</strong>
                        </div>
                    @endif
                    @if ($editable && can('crear-tesoreria-crown-movimiento', false))
                        <button type="submit" class="btn btn-primary" name="accion" value="guardar" id="btn-guardar-cantidades">Guardar borrador</button>
                        <button type="submit" class="btn btn-outline-primary ml-1" name="accion" value="pedir-firmas">Pedir firmas</button>
                        <span class="text-muted small ml-2">Pedir firmas guarda esta pantalla. Después el cajero firma con número y PIN, y tesorería con usuario y clave.</span>
                    @endif
                </form>

                @php
                    $firmas = [
                        'entrega' => [$etiquetaEntrega, $comprobante->entregaUsuario, $comprobante->entrega_at],
                        'recibe' => [$etiquetaRecibe, $comprobante->recibeUsuario, $comprobante->recibe_at],
                    ];
                @endphp
                <div class="row mt-4">
                    @foreach ($firmas as $lado => [$etiqueta, $usuarioFirma, $cuando])
                        <div class="col-md-6">
                            <div class="border rounded p-3 mb-3 h-100">
                                <strong>{{ $etiqueta }}</strong>
                                <p class="mb-2">{{ \App\Support\Caja\TesoreriaCrownFirmaSupport::textoFirma($usuarioFirma, $cuando, $lado === $ladoCajero ? ($cajeroFirma->nombre ?? null) : null) }}</p>
                                @if ($lado === $ladoCajero && $comprobante->estado === 'P' && $cuando === null && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('listar-tesoreria-crown-movimiento', false))
                                    <form method="POST" action="{{ route('firmar_cajero_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-campos="1" data-enter-boton="1">
                                        @csrf
                                        <p class="small text-muted mb-2">{{ $cajeroFirma->nombre ?? 'El cajero' }} firma en este teclado con su número y su PIN.</p>
                                        <div class="form-row">
                                            <div class="col-6 mb-2">
                                                <input type="text" name="cajero_numero" class="form-control form-control-sm" placeholder="Número de cajero" inputmode="numeric" autocomplete="off" required value="{{ old('cajero_numero') }}">
                                            </div>
                                            <div class="col-6 mb-2">
                                                <input type="text" name="cajero_pin" class="form-control form-control-sm tc-pin-cajero" placeholder="PIN actual" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" @unless (session('tc_pin_nuevo')) required @endunless>
                                            </div>
                                        </div>
                                        @include('caja.tesoreria_crown.partials.pin_nuevo_cajero', ['nuevoName' => 'cajero_pin_nuevo', 'repetirName' => 'cajero_pin_repetir'])
                                        <button type="submit" class="btn btn-success btn-sm">Firmar {{ $etiqueta }}</button>
                                    </form>
                                    @if (can('editar-tesoreria-crown-movimiento', false))
                                        <details class="mt-2 small">
                                            <summary class="text-muted">El cajero se olvidó el PIN</summary>
                                            <form method="POST" action="{{ route('blanquear_pin_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-campos="1" class="mt-2">
                                                @csrf
                                                <p class="text-muted mb-2">Tesorería blanquea el PIN; en la próxima firma el cajero elige uno nuevo.</p>
                                                <input type="text" name="motivo_pin" class="form-control form-control-sm mb-2" placeholder="Motivo" maxlength="300" required>
                                                <div class="form-row">
                                                    <div class="col-6 mb-2">
                                                        <input type="text" name="pin_firma_usuario" class="form-control form-control-sm" placeholder="Usuario tesorería" autocomplete="off" required>
                                                    </div>
                                                    <div class="col-6 mb-2">
                                                        <input type="password" name="pin_firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-outline-warning btn-sm">Blanquear PIN</button>
                                            </form>
                                        </details>
                                    @endif
                                @endif
                                @if ($lado === $ladoTesoreria && $comprobante->estado === 'P' && $cuando === null && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                                    <form method="POST" action="{{ route('firmar_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-boton="1" data-enter-campos="1">
                                        @csrf
                                        <p class="small text-muted">Tesorería firma con usuario y clave del sistema.</p>
                                        <div class="form-row">
                                            <div class="col-6 mb-2">
                                                <input type="text" name="firma_usuario" class="form-control form-control-sm" placeholder="Usuario" autocomplete="off" required>
                                            </div>
                                            <div class="col-6 mb-2">
                                                <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-success btn-sm">Firmar {{ $etiqueta }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @php
                    $hayFirma = $comprobante->entrega_at !== null || $comprobante->recibe_at !== null;
                @endphp
                @if ($comprobante->estado === 'P' && $comprobante->revierte_id === null && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('crear-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('volver_borrador_tesoreria_crown_movimiento', $comprobante->id) }}" class="d-inline mb-3" onsubmit="return confirm('{{ $hayFirma ? '¿Volver a borrador? Se borran las firmas.' : '¿Volver a cargar las cantidades? Todavía no hay firmas.' }}');">
                        @csrf
                        <button type="submit" class="btn btn-outline-secondary btn-sm">{{ $hayFirma ? 'Volver a borrador' : 'Modificar cantidades' }}</button>
                    </form>
                @endif

                @if (in_array($comprobante->estado, ['B', 'P'], true) && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <form method="POST" action="{{ route('noconforme_tesoreria_crown_movimiento', $comprobante->id) }}" class="form-inline d-inline ml-2" data-enter-campos="1">
                        @csrf
                        <input type="text" name="observacion" class="form-control form-control-sm mr-2" maxlength="500" placeholder="Motivo" required>
                        <button type="submit" class="btn btn-outline-danger btn-sm">No conforme</button>
                    </form>
                @endif

                @if (in_array($comprobante->estado, \App\Support\Caja\TesoreriaCrownComprobanteSupport::SIN_RESOLVER, true) && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <div class="card card-outline card-danger mt-3 mb-0">
                        <div class="card-body py-2">
                            <strong>Resolución de auditoría</strong>
                            <p class="small text-muted mb-2">La caja no cierra hasta resolverlo. Firma quien tenga permiso de auditoría.
                                {{ $comprobante->revierte_id !== null ? 'Es una reversa: solo se anula, y el original vuelve a poder revertirse.' : 'Corregir lo devuelve a borrador sin firmas; anular lo saca de la caja.' }}</p>
                            <form method="POST" action="{{ route('resolver_noconforme_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-campos="1">
                                @csrf
                                <div class="form-row">
                                    <div class="col-md-2 mb-2">
                                        <select name="accion" class="form-control form-control-sm" required>
                                            @if ($comprobante->revierte_id === null)
                                                <option value="corregir">Corregir</option>
                                            @endif
                                            <option value="anular">Anular</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <input type="text" name="resolucion" class="form-control form-control-sm" maxlength="500" placeholder="Resolución" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <input type="text" name="firma_usuario" class="form-control form-control-sm" placeholder="Usuario auditoría" autocomplete="off" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <button type="submit" class="btn btn-danger btn-sm">Resolver</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($comprobante->estado === 'C' && $comprobante->revierte_id === null && ! $yaRevertido && empty($cajaCerrada) && $comprobante->jornada->estaAbierta() && can('editar-tesoreria-crown-movimiento', false))
                    <details id="tc-revertir" class="mt-2" @if ($errors->has('motivo') || old('motivo')) open @endif>
                        <summary class="btn btn-outline-danger btn-sm"><i class="fa fa-undo"></i> Revertir</summary>
                        <form method="POST" action="{{ route('revertir_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-campos="1" class="mt-2" onsubmit="return confirm('¿Revertir este comprobante? La reversa queda pendiente de las dos firmas.');">
                            @csrf
                            <p class="small text-muted mb-2">Autoriza tesorería con usuario y clave. La reversa no se modifica: si sale mal, se marca no conforme y auditoría la anula.</p>
                            <div class="form-row">
                                <div class="col-md-5 mb-2">
                                    <input type="text" name="motivo" class="form-control form-control-sm" maxlength="500" placeholder="Motivo de la reversa" value="{{ old('motivo') }}" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <input type="text" name="firma_usuario" class="form-control form-control-sm" placeholder="Usuario tesorería" autocomplete="off" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <input type="password" name="firma_password" class="form-control form-control-sm" placeholder="Clave" autocomplete="new-password" required>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="submit" class="btn btn-danger btn-sm">Revertir</button>
                                </div>
                            </div>
                        </form>
                    </details>
                @endif
                @if (request()->input('vista') === 'consulta')
                    <button type="button" class="btn btn-secondary btn-sm ml-2" onclick="window.close()">Cerrar solapa</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
