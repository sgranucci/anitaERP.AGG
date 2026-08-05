<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rendici&oacute;n m&aacute;quinas {{ $rendicion->codigo }}</title>
    @include('caja.rendiciongastronomia.partials.estilos_comprobante_pdf')
    <style>
        .tabla-cuenta thead th { background: #85C1E9; color: #17202A; }
        .fila-total td { background: #e8f4fc; font-weight: bold; }
        .totales-box { margin-top: 4px; }
        .totales-box td.lbl { width: 45%; background: #f0f0f0; font-weight: bold; }
        .dos-cols { width: 100%; border: none !important; margin-bottom: 8px; }
        .dos-cols > tbody > tr > td { border: none !important; vertical-align: top; width: 50%; padding: 0 4px 0 0; }
        .dos-cols > tbody > tr > td + td { padding: 0 0 0 4px; }
    </style>
</head>
<body>
@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
    $calcVars = is_array($rendicion->calc_json['variables'] ?? null) ? $rendicion->calc_json['variables'] : [];
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
    $inp = function (string $k) use ($inputs) {
        return (float) ($inputs[$k] ?? $inputs['inputs.'.$k] ?? 0);
    };
    $calc = function (string $k) use ($calcVars) {
        return (float) ($calcVars[$k] ?? $calcVars['calc.'.$k] ?? 0);
    };
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($rendicion->empresa?->nombre);
    $fondoFijo = $calc('fondo_fijo');
    if (abs($fondoFijo) < 0.00001) {
        $fondoFijo = (float) $rendicion->fondo_inicial + $calc('comprobante');
    }
@endphp

<table class="cabecera-doc">
    <tr>
        <td style="width: 35%;">
            @if (! empty($logo['uri']))
                <img src="{{ $logo['uri'] }}" alt="Logo" class="logo">
            @endif
        </td>
        <td style="width: 65%; text-align: right;">
            <h1>Rendici&oacute;n de m&aacute;quinas</h1>
            <div class="subtitulo">
                C&oacute;digo: <strong>{{ $rendicion->codigo }}</strong>
                @if ($rendicion->nro_oper_anita)
                    @if (config('rendicion_maquina_anita.sincronizar'))
                        &middot; Nro. Anita: {{ $rendicion->nro_oper_anita }}
                    @else
                        &middot; Nro.: {{ $rendicion->nro_oper_anita }}
                    @endif
                @endif
            </div>
            <div class="muted">PDF generado: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table>
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ $rendicion->empresa?->nombre }}</td>
        <td class="lbl">Fecha</td>
        <td>{{ optional($rendicion->fecha)->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="lbl">Turno</td>
        <td>{{ $rendicion->turno_label }} ({{ $rendicion->turno }})</td>
        <td class="lbl">Estado</td>
        <td>{{ $rendicion->estado_label }}</td>
    </tr>
    <tr>
        <td class="lbl">Supervisor</td>
        <td>{{ $rendicion->supervisorUsuario?->nombre ?: '—' }}</td>
        <td class="lbl">Cajero</td>
        <td>{{ $rendicion->cajeroUsuario?->nombre ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Auxiliar</td>
        <td>{{ $rendicion->auxiliarUsuario?->nombre ?: '—' }}</td>
        <td class="lbl">Registr&oacute;</td>
        <td>{{ $rendicion->creoUsuario?->nombre ?: '—' }}</td>
    </tr>
</table>

<table class="dos-cols">
    <tr>
        <td>
            <h2>Totales de cierre</h2>
            <table class="totales-box">
                <tr><td class="lbl">Fondo inicial</td><td class="num">{{ $fmt($rendicion->fondo_inicial) }}</td></tr>
                <tr><td class="lbl">Comprobante</td><td class="num">{{ $fmt($calc('comprobante')) }}</td></tr>
                <tr><td class="lbl">Fondo fijo tesoro</td><td class="num">{{ $fmt($fondoFijo) }}</td></tr>
                <tr><td class="lbl">Drop rodillo bruto</td><td class="num">{{ $fmt($inp('drop_billete_bruto') ?: ($inp('drop_billete') + $inp('impuesto_drop'))) }}</td></tr>
                <tr><td class="lbl">Impuesto drop</td><td class="num">{{ $fmt($inp('impuesto_drop')) }}</td></tr>
                <tr><td class="lbl">Drop rodillo neto</td><td class="num">{{ $fmt($calc('drop_bill_rodillo') ?: $inp('drop_billete')) }}</td></tr>
                <tr><td class="lbl">Total ingreso</td><td class="num">{{ $fmt($rendicion->total_ingreso) }}</td></tr>
                <tr><td class="lbl">Total salida</td><td class="num">{{ $fmt($rendicion->total_salida) }}</td></tr>
                <tr class="fila-total"><td class="lbl">Resultado turno</td><td class="num">{{ $fmt($rendicion->resultado_turno) }}</td></tr>
                <tr><td class="lbl">Fondo cierre</td><td class="num">{{ $fmt($rendicion->fondo_cierre) }}</td></tr>
                <tr class="fila-total"><td class="lbl">Transferencia</td><td class="num">{{ $fmt($rendicion->transferencia) }}</td></tr>
                @if ($rendicion->turno === 'C')
                    <tr><td class="lbl">Dif. caja</td><td class="num">{{ $fmt($rendicion->dif_caja) }}</td></tr>
                @endif
                @php
                    // Ventas ya netas: no restar impuesto_venta (evitar doble descuento).
                    $winPdf = round(
                        $calc('drop_bill_rodillo') + $calc('drop_bill_ruleta')
                        + $inp('dropqr_rodillo') + $inp('dropqr_ruleta')
                        + $inp('venta_ficha') + $inp('venta_ruleta')
                        - $inp('pago_manual')
                        - $inp('tito')
                        - $inp('tito_ruleta'),
                        2
                    );
                @endphp
                <tr class="fila-total"><td class="lbl">WIN</td><td class="num">{{ $fmt($winPdf) }}</td></tr>
            </table>
        </td>
        <td>
            <h2>Datos principales</h2>
            <table class="tabla-cuenta">
                <thead>
                    <tr>
                        <th>Concepto</th>
                        <th class="num">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        'drop_billete' => 'Drop billete (neto)',
                        'drop_ruleta' => 'Drop ruleta',
                        'drop_bill_ant' => 'Drop rodillo anterior',
                        'drop_rul_ant' => 'Drop ruleta anterior',
                        'venta_ficha' => 'Venta fichas (slots)',
                        'venta_ruleta' => 'Venta ruletas',
                        'tito' => 'Tito rodillos',
                        'tito_ruleta' => 'Tito ruletas',
                        'pago_manual' => 'Pago manual',
                        'salida_ruleta' => 'Salidas ruleta',
                        'vale_rep_fondo' => 'Vale rep. fondo',
                        'deposito' => 'Dep&oacute;sito',
                        'sobrantes' => 'Sobrantes',
                        'vales' => 'Vales',
                        'reintegros' => 'Reintegros',
                        'variacion_ff' => 'Variaci&oacute;n FF',
                        'pago_diferido' => 'Pago diferido',
                        'impuesto_venta' => 'Impuesto venta',
                        'impuesto_qr' => 'Impuesto QR',
                        'impuesto_pago' => 'Impuesto / canje gastro',
                    ] as $clave => $etiqueta)
                        @php
                            $valorFila = $clave === 'vale_rep_fondo'
                                ? $calc('vale_rep_fondo')
                                : ($clave === 'deposito' ? ($calc('deposito') ?: $inp('deposito')) : $inp($clave));
                        @endphp
                        @if (abs($valorFila) >= 0.005)
                            <tr>
                                <td>{!! $etiqueta !!}</td>
                                <td class="num">{{ $fmt($valorFila) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<h2>Valores (cuentas de caja)</h2>
<table class="tabla-cuenta">
    <thead>
        <tr>
            <th style="width:12%">C&oacute;digo</th>
            <th>Cuenta</th>
            <th class="num" style="width:20%">Monto</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rendicion->valores as $valor)
            <tr>
                <td>{{ $valor->cuentacaja?->codigo }}</td>
                <td>{{ $valor->cuentacaja?->etiquetaOperaciones() }}</td>
                <td class="num">{{ $fmt($valor->monto) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin valores cargados.</td></tr>
        @endforelse
        <tr class="fila-total">
            <td colspan="2">Total valores</td>
            <td class="num">{{ $fmt($rendicion->valores->sum('monto')) }}</td>
        </tr>
    </tbody>
</table>

<h2>Apertura de gastos</h2>
<table class="tabla-cuenta">
    <thead>
        <tr>
            <th style="width:12%">C&oacute;digo</th>
            <th>Concepto</th>
            <th class="num" style="width:20%">Monto</th>
        </tr>
    </thead>
    <tbody>
        @php $gastosConMonto = $rendicion->gastos->filter(fn ($g) => abs((float) $g->monto) >= 0.005); @endphp
        @forelse ($gastosConMonto as $gasto)
            <tr>
                <td>{{ $gasto->aperturaGasto?->codigo }}</td>
                <td>{{ $gasto->aperturaGasto?->nombre }}</td>
                <td class="num">{{ $fmt($gasto->monto) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">Sin gastos.</td></tr>
        @endforelse
        <tr class="fila-total">
            <td colspan="2">Total gastos</td>
            <td class="num">{{ $fmt($gastosConMonto->sum('monto')) }}</td>
        </tr>
    </tbody>
</table>

@if ($rendicion->observacion)
    <h2>Observaci&oacute;n</h2>
    <p class="bloque-obs">{{ $rendicion->observacion }}</p>
@endif
</body>
</html>
