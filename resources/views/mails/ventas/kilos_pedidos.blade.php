<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kilos pedidos</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">{{ $perfilNombre }}</h2>
<p style="margin:0 0 12px 0;">
    Fecha de entrega: <strong>{{ \Carbon\Carbon::parse($fechaEntrega)->format('d/m/Y') }}</strong><br>
    Repartos: <strong>{{ $repartoTexto }}</strong>
</p>

<p style="margin:0 0 12px 0;">
    Totales:
    <strong>{{ number_format((float) ($totalesMerge['kilo'] ?? 0), 2, ',', '.') }}</strong> kg ·
    {{ number_format((float) ($totalesMerge['pieza'] ?? 0), 2, ',', '.') }} piezas
</p>

<p style="margin:0 0 16px 0;">
    Adjunto el PDF (mismo formato que l-pedkilo: total por artículo x agrupación + repartos con pedidos).
</p>

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · reemplazo de l-pedkilo -b
</p>
</body>
</html>
