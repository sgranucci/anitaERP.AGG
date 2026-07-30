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
        .totales-box { margin-top: 10px; }
        .totales-box td.lbl { width: 40%; }
    </style>
</head>
<body>
@php
    $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
    $calcVars = is_array($rendicion->calc_json['variables'] ?? null) ? $rendicion->calc_json['variables'] : [];
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
    $inp = function (string $k) use ($inputs) {
        return (float) ($inputs[$k] ?? $inputs['inputs.'.$k] ?? 0);
    };
    $calc = function (string $k) use ($calcVars) {
        return (float) ($calcVars[$k] ?? $calcVars['calc.'.$k] ?? 0);
    };
@endphp
    <h1>Rendici&oacute;n de m&aacute;quinas</h1>
    <div class="subtitulo">
        C&oacute;digo: <strong>{{ $rendicion->codigo }}</strong>
        @if ($rendicion->nro_oper_anita)
            &middot; Nro. Anita: {{ $rendicion->nro_oper_anita }}
        @endif
        &middot; Empresa: {{ $rendicion->empresa?->nombre }}
        &middot; Fecha: {{ optional($rendicion->fecha)->format('d/m/Y') }}
        &middot; Turno: {{ $rendicion->turno_label }} ({{ $rendicion->turno }})
        &middot; Estado: {{ $rendicion->estado_label }}
    </div>
    <div class="muted" style="margin-bottom:10px;">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if ($rendicion->creoUsuario?->nombre)
            &middot; Registr&oacute;: {{ $rendicion->creoUsuario->nombre }}
        @endif
        @if ($rendicion->supervisorUsuario?->nombre)
            &middot; Supervisor: {{ $rendicion->supervisorUsuario->nombre }}
        @endif
        @if ($rendicion->cajeroUsuario?->nombre)
            &middot; Cajero: {{ $rendicion->cajeroUsuario->nombre }}
        @endif
    </div>

    <h2>Totales de cierre</h2>
    <table class="totales-box">
        <tr><td class="lbl">Fondo inicial</td><td class="num">{{ $fmt($rendicion->fondo_inicial) }}</td></tr>
        <tr><td class="lbl">Comprobante</td><td class="num">{{ $fmt($calc('comprobante')) }}</td></tr>
        <tr><td class="lbl">Total ingreso</td><td class="num">{{ $fmt($rendicion->total_ingreso) }}</td></tr>
        <tr><td class="lbl">Total salida</td><td class="num">{{ $fmt($rendicion->total_salida) }}</td></tr>
        <tr class="fila-total"><td class="lbl">Resultado turno</td><td class="num">{{ $fmt($rendicion->resultado_turno) }}</td></tr>
        <tr><td class="lbl">Fondo cierre</td><td class="num">{{ $fmt($rendicion->fondo_cierre) }}</td></tr>
        <tr class="fila-total"><td class="lbl">Transferencia</td><td class="num">{{ $fmt($rendicion->transferencia) }}</td></tr>
        @if ($rendicion->turno === 'C')
            <tr><td class="lbl">Dif. caja</td><td class="num">{{ $fmt($rendicion->dif_caja) }}</td></tr>
        @endif
    </table>

    <h2>Inputs principales</h2>
    <table class="tabla-cuenta">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ([
                'drop_billete' => 'Drop billete',
                'drop_ruleta' => 'Drop ruleta',
                'tito' => 'Tito',
                'tito_ruleta' => 'Tito ruleta',
                'hopper' => 'Hopper',
                'venta_ficha' => 'Venta ficha',
                'deposito' => 'Dep&oacute;sito',
                'sobrantes' => 'Sobrantes',
                'pago_manual' => 'Pago manual',
                'pago_diferido' => 'Pago diferido',
                'variacion_ff' => 'Variaci&oacute;n FF',
            ] as $clave => $etiqueta)
                <tr>
                    <td>{!! $etiqueta !!}</td>
                    <td class="num">{{ $fmt($inp($clave)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Valores (cuentas de caja)</h2>
    <table class="tabla-cuenta">
        <thead>
            <tr>
                <th>C&oacute;digo</th>
                <th>Cuenta</th>
                <th class="num">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rendicion->valores as $valor)
                <tr>
                    <td>{{ $valor->cuentacaja?->codigo }}</td>
                    <td>{{ $valor->cuentacaja?->nombre }}</td>
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
                <th>C&oacute;digo</th>
                <th>Concepto</th>
                <th class="num">Monto</th>
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
