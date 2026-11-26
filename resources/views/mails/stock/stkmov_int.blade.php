<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de stock</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Movimientos de stock interno</h2>
<p style="margin:0 0 12px 0;">
    Fecha: <strong>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</strong>
    · grupos G1–G6 · Villafranca + Bierzo
</p>

<p style="margin:0 0 12px 0;">
    Artículos: <strong>{{ (int) ($totales['filas'] ?? 0) }}</strong><br>
    Saldo total: <strong>{{ number_format((float) ($totales['saldo'] ?? 0), 2, ',', '.') }}</strong><br>
    Unidades: <strong>{{ number_format((float) ($totales['unidades'] ?? 0), 0, ',', '.') }}</strong>
</p>

<p style="margin:0 0 16px 0;">
    Adjunto el PDF con Art|Desc|Saldo|T.Unidades (suma Villafranca + Bierzo)
    y el cuadro de porcentajes por grupo.
</p>

@php
    $filasPct = $porcentajes['filas'] ?? [];
    $totPct = $porcentajes['totales'] ?? [];
@endphp
@if ($filasPct !== [])
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <thead>
            <tr style="background:#85C1E9;">
                <th align="left">Agrupación</th>
                <th align="right">% Kg. venta</th>
                <th align="right">% $ venta</th>
                <th align="right">% Bonific.</th>
                <th align="right">% Bonif. ABS</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filasPct as $filaPct)
                <tr>
                    <td>{{ $filaPct['etiqueta'] ?? '' }}</td>
                    <td align="right">{{ number_format((float) ($filaPct['pct_kg'] ?? 0), 0, ',', '.') }}</td>
                    <td align="right">{{ number_format((float) ($filaPct['pct_pesos'] ?? 0), 0, ',', '.') }}</td>
                    <td align="right">{{ number_format((float) ($filaPct['pct_bonif'] ?? 0), 0, ',', '.') }}</td>
                    <td align="right">{{ number_format((float) ($filaPct['pct_bonif_abs'] ?? 0), 1, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total final</strong></td>
                <td align="right"><strong>{{ number_format((float) ($totPct['pct_kg'] ?? 0), 0, ',', '.') }}</strong></td>
                <td align="right"><strong>{{ number_format((float) ($totPct['pct_pesos'] ?? 0), 0, ',', '.') }}</strong></td>
                <td align="right"><strong>{{ number_format((float) ($totPct['pct_bonif'] ?? 0), 0, ',', '.') }}</strong></td>
                <td align="right"><strong>{{ number_format((float) ($totPct['pct_bonif_abs'] ?? 0), 1, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
    @if (! empty($graficoPng))
        <p style="margin:16px 0 0 0;">
            <img src="{{ $message->embedData($graficoPng, 'stkmov-porcentajes.png', 'image/png') }}" alt="Porcentajes por agrupación" width="640">
        </p>
    @endif
@endif

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · reemplazo de l-stkmovint
</p>
</body>
</html>
