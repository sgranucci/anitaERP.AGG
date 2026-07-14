<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Z — transmisión faltante</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $a = $analisis;
@endphp

<h2 style="margin:0 0 8px 0;">Comandas no incluidas en el Informe Z del cierre</h2>
<p style="margin:0 0 12px 0;">
    Empresa: <strong>{{ $a['empresa_nombre'] ?? '—' }}</strong><br>
    Jornada: <strong>{{ $a['fecha_jornada_fmt'] ?? ($a['fecha_jornada'] ?? '—') }}</strong>
    (#{{ (int) ($a['jornada_id'] ?? 0) }})<br>
    Cierre jornada: <strong>{{ $a['cierre_jornada_en_fmt'] ?? '—' }}</strong>
</p>

<p style="color:#b45309; font-weight:bold; margin:0 0 16px 0;">
    Waitry transmitió después del snapshot de cierre {{ (int) ($a['cantidad_comandas'] ?? 0) }} comanda(s)
    por $ {{ $fmt($a['total_faltante'] ?? 0) }} que no estaban en el Informe Z histórico.
    El Z del cierre <u>no se modifica</u>; Tesorería debe sumar este importe al presentar.
</p>

@if (! empty($a['comandas']))
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%; margin-bottom:16px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Comanda</th>
            <th align="left">Waitry #</th>
            <th align="left">Medio</th>
            <th align="left">Colocada</th>
            <th align="left">Tótem / mesa</th>
            <th align="right">Monto</th>
        </tr>
        @foreach ($a['comandas'] as $c)
            <tr>
                <td>{{ $c['display_id'] ?? '—' }}</td>
                <td>{{ (int) ($c['waitry_order_id'] ?? 0) }}</td>
                <td>{{ $c['medio_label'] ?? ($c['tipo_medio'] ?? '—') }}</td>
                <td>{{ $c['placed_at'] ?? '—' }}</td>
                <td>
                    {{ $c['waitry_layout_name'] ?? '' }}
                    @if (! empty($c['waitry_table_name']))
                        / {{ $c['waitry_table_name'] }}
                    @endif
                </td>
                <td align="right">$ {{ $fmt($c['monto'] ?? 0) }}</td>
            </tr>
        @endforeach
    </table>
@endif

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; margin-bottom:16px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Concepto</th>
        <th align="right">Importe</th>
    </tr>
    <tr>
        <td>Informe Z al cierre (histórico)</td>
        <td align="right">$ {{ $fmt($a['total_z_historico'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>+ Comandas no transmitidas a tiempo</td>
        <td align="right">$ {{ $fmt($a['total_faltante'] ?? 0) }}</td>
    </tr>
    <tr style="font-weight:bold;">
        <td>= Total Tesorería (presentación / facturación CAEA)</td>
        <td align="right">$ {{ $fmt($a['total_tesoreria'] ?? 0) }}</td>
    </tr>
</table>

<p style="margin-top:16px; font-size:12px; color:#666;">
    Verificado {{ $a['calculado_en'] ?? now()->format('Y-m-d H:i:s') }} ·
    Relectura Waitry $ {{ $fmt($a['total_z_relectura'] ?? 0) }} ·
    Diff totales $ {{ $fmt($a['diff_totales'] ?? 0) }}
</p>
</body>
</html>
