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
        .hdr { text-align: center; padding: 10px 8px; font-weight: bold; }
        .hdr-sub { text-align: center; padding: 6px 8px; font-size: 10px; }
        table.liq { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.liq th, table.liq td { border: 1px solid #BAC6D4; padding: 5px 6px; }
        table.liq th { background: #2E75B6; color: #fff; font-size: 9.5px; }
        .num { text-align: right; white-space: nowrap; }
        .sec { font-weight: bold; }
        .tot-cod { background: #2E75B6; color: #fff; font-weight: bold; }
        .tot-ing { background: #1F4E6B; color: #fff; font-weight: bold; font-size: 11px; }
        .cuenta { color: #595959; font-size: 9px; }
    </style>
</head>
<body>
@php
    $empresaLabel = $empresaLabel ?? '';
    $periodoLabel = $periodoLabel ?? '';
    $estructura = $estructura ?? ['secciones' => [], 'total_q1' => 0, 'total_q2' => 0, 'total' => 0];
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

<div class="navy hdr">{{ $empresaLabel }}</div>
<div class="navy hdr-sub">Liquidación de SICORE &nbsp;·&nbsp; {{ $periodoLabel }}</div>

<table class="liq">
    <thead>
        <tr>
            <th style="width:18%;">Cuenta</th>
            <th style="width:40%;">Concepto</th>
            <th style="width:14%;" class="num">1ra Quincena</th>
            <th style="width:14%;" class="num">2da Quincena</th>
            <th style="width:14%;" class="num">Total Mensual</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($estructura['secciones'] as $sec)
            <tr>
                <td colspan="5" class="blue-l sec">{{ $sec['nombre'] }} — Código {{ $sec['codigo'] }}</td>
            </tr>
            @foreach ($sec['filas'] as $i => $fila)
                @php
                    $filaClass = ($i % 2) ? 'zebra' : '';
                @endphp
                <tr class="{{ $filaClass }}">
                    <td class="cuenta">{{ $fila['cuenta'] }}</td>
                    <td>{{ $fila['concepto'] }}</td>
                    <td class="num">{{ abs((float) $fila['q1']) < 0.005 ? '—' : $fmt($fila['q1']) }}</td>
                    <td class="num">{{ abs((float) $fila['q2']) < 0.005 ? '—' : $fmt($fila['q2']) }}</td>
                    <td class="num">{{ abs((float) $fila['total']) < 0.005 ? '—' : $fmt($fila['total']) }}</td>
                </tr>
            @endforeach
            <tr class="tot-cod">
                <td colspan="2">Total Código {{ $sec['codigo'] }}</td>
                <td class="num">{{ $fmt($sec['subtotal_q1']) }}</td>
                <td class="num">{{ $fmt($sec['subtotal_q2']) }}</td>
                <td class="num">{{ $fmt($sec['subtotal']) }}</td>
            </tr>
        @endforeach
        <tr class="tot-ing">
            <td colspan="2">TOTAL A INGRESAR</td>
            <td class="num">{{ $fmt($estructura['total_q1']) }}</td>
            <td class="num">{{ $fmt($estructura['total_q2']) }}</td>
            <td class="num">{{ $fmt($estructura['total']) }}</td>
        </tr>
    </tbody>
</table>

@if (! empty($autocontrol))
    <p style="margin-top:14px;font-size:9px;color:#595959;">
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
