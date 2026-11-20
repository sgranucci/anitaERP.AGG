<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Liquidación SICORE</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1A1A1A; margin: 18px; }
        .navy { background-color: #1F4E6B; color: #fff; }
        .blue { background-color: #2E75B6; color: #fff; }
        .blue-l { background-color: #DDEBF7; color: #1F4E6B; }
        .zebra { background-color: #F3F7FB; }
        .muted { color: #595959; }
        .verde { color: #1a7f37; font-weight: bold; }
        .hdr { text-align: center; padding: 10px 8px; font-weight: bold; }
        .hdr-sub { text-align: center; padding: 6px 8px; font-size: 10px; }
        table.liq { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.liq th, table.liq td { border: 1px solid #BAC6D4; padding: 5px 6px; }
        table.liq th { background: #2E75B6; color: #fff; font-size: 9.5px; }
        .num { text-align: right; white-space: nowrap; }
        .sec { font-weight: bold; margin: 14px 0 4px; }
        .tot-cod { background: #F4F6F9; font-weight: bold; }
        .tot-ing { background: #1F4E6B; color: #fff; font-weight: bold; font-size: 11px; }
        .nota { margin-top: 10px; font-size: 9px; color: #595959; }
    </style>
</head>
<body>
@php
    $empresaLabel = $empresaLabel ?? '';
    $periodoLabel = $periodoLabel ?? '';
    $cuadro = $cuadro ?? [];
    $tipo = (string) ($cuadro['tipo'] ?? '1q');
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $fmtOEmpty = static function ($n) use ($fmt) {
        if ($n === null) {
            return '—';
        }
        return abs((float) $n) < 0.005 ? '—' : $fmt($n);
    };
@endphp

<div class="navy hdr">{{ $empresaLabel }}</div>
<div class="navy hdr-sub">Liquidación de SICORE &nbsp;·&nbsp; {{ $periodoLabel }}</div>

@if ($tipo === 'dj')
    <div class="sec">1. Total del mes</div>
    <table class="liq">
        <thead>
            <tr>
                <th style="width:10%;">Código</th>
                <th style="width:22%;">Impuesto</th>
                <th style="width:17%;" class="num">1ra quincena (01 al 15)</th>
                <th style="width:17%;" class="num">2da quincena (16 a fin)</th>
                <th style="width:17%;" class="num">Sueldos mes ant.</th>
                <th style="width:17%;" class="num">Total del mes</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($cuadro['totales_mes'] ?? []) as $i => $fila)
                <tr class="{{ ($i % 2) ? 'zebra' : '' }}">
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['impuesto'] }}</td>
                    <td class="num">{{ $fmtOEmpty($fila['q1'] ?? null) }}</td>
                    <td class="num">{{ $fmtOEmpty($fila['q2'] ?? null) }}</td>
                    <td class="num">{{ $fmtOEmpty($fila['sueldos'] ?? null) }}</td>
                    <td class="num">{{ $fmt($fila['total'] ?? 0) }}</td>
                </tr>
            @endforeach
            @php $sum = $cuadro['totales_mes_sumas'] ?? []; @endphp
            <tr class="tot-cod">
                <td colspan="2">Total</td>
                <td class="num">{{ $fmt($sum['q1'] ?? 0) }}</td>
                <td class="num">{{ $fmt($sum['q2'] ?? 0) }}</td>
                <td class="num">{{ $fmt($sum['sueldos'] ?? 0) }}</td>
                <td class="num">{{ $fmt($sum['total'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="sec">2. Saldo de la DJ</div>
    <table class="liq">
        <thead>
            <tr>
                <th style="width:10%;">Código</th>
                <th style="width:18%;" class="num">Total del mes</th>
                <th style="width:18%;" class="num">(−) Saldo a favor período ant.</th>
                <th style="width:18%;" class="num">(−) Ingresos a cuenta</th>
                <th style="width:18%;" class="num">Saldo a pagar ARCA</th>
                <th style="width:18%;" class="num">Saldo a favor período sig.</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($cuadro['saldos'] ?? []) as $i => $fila)
                <tr class="{{ ($i % 2) ? 'zebra' : '' }}">
                    <td>{{ $fila['codigo'] }}</td>
                    <td class="num">{{ $fmt($fila['total'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($fila['saf'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($fila['ingresos'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($fila['a_pagar'] ?? 0) }}</td>
                    <td class="num {{ ((float) ($fila['a_favor_sig'] ?? 0)) > 0.005 ? 'verde' : '' }}">
                        {{ $fmt($fila['a_favor_sig'] ?? 0) }}
                    </td>
                </tr>
            @endforeach
            @php $ss = $cuadro['saldos_sumas'] ?? []; @endphp
            <tr class="tot-ing">
                <td>Total</td>
                <td class="num">{{ $fmt($ss['total'] ?? 0) }}</td>
                <td class="num">{{ $fmt($ss['saf'] ?? 0) }}</td>
                <td class="num">{{ $fmt($ss['ingresos'] ?? 0) }}</td>
                <td class="num">{{ $fmt($ss['a_pagar'] ?? 0) }}</td>
                <td class="num">{{ $fmt($ss['a_favor_sig'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($cuadro['ingresos_detalle']))
        <div class="sec">Detalle de ingresos a cuenta</div>
        <table class="liq" style="width:55%;">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Fecha</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cuadro['ingresos_detalle'] as $ing)
                    <tr>
                        <td>{{ $ing['codigo'] }}</td>
                        <td>
                            @php
                                $f = (string) ($ing['fecha'] ?? '');
                                try {
                                    $f = $f !== '' ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '';
                                } catch (\Throwable $e) {
                                }
                            @endphp
                            {{ $f }}
                        </td>
                        <td class="num">{{ $fmt($ing['importe'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@else
    <table class="liq">
        <thead>
            <tr>
                <th style="width:12%;">Código</th>
                <th style="width:22%;">Impuesto</th>
                <th style="width:28%;">Origen</th>
                <th style="width:19%;" class="num">Retenciones</th>
                <th style="width:19%;" class="num">A pagar (VEP a cuenta)</th>
            </tr>
        </thead>
        <tbody>
            @foreach (($cuadro['filas_1q'] ?? []) as $i => $fila)
                <tr class="{{ ($i % 2) ? 'zebra' : '' }}">
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['impuesto'] }}</td>
                    <td>{{ $fila['origen'] }}</td>
                    <td class="num">{{ $fmt($fila['retenciones'] ?? 0) }}</td>
                    <td class="num">{{ $fmt($fila['a_pagar'] ?? 0) }}</td>
                </tr>
            @endforeach
            <tr class="tot-ing">
                <td colspan="3">Total</td>
                <td class="num">{{ $fmt($cuadro['total_retenciones'] ?? 0) }}</td>
                <td class="num">{{ $fmt($cuadro['total_a_pagar'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>
    @if (! empty($cuadro['leyenda']))
        <p class="nota">{{ $cuadro['leyenda'] }}</p>
    @endif
@endif

@if (! empty($autocontrol))
    <p class="nota">
        Autocontrol:
        @if (! empty($autocontrol['ok']))
            TODO CUADRA.
        @else
            HAY DIFERENCIAS — revisar listados de respaldo.
        @endif
    </p>
@endif
</body>
</html>
