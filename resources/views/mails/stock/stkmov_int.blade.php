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
    Adjunto el PDF con Art|Desc|Saldo|T.Unidades (suma Villafranca + Bierzo).
</p>

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · reemplazo de l-stkmovint
</p>
</body>
</html>
