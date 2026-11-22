<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notas de Credito de Dto.</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Notas de Credito de Dto.</h2>
<p style="margin:0 0 12px 0;">
    Fecha: <strong>{{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}</strong>
</p>

<p style="margin:0 0 12px 0;">
    Listas 1 y 8 (&gt;4%): <strong>{{ (int) ($totales['lista'] ?? 0) }}</strong>
    · monto {{ number_format((float) ($totales['monto_lista'] ?? 0), 2, ',', '.') }}<br>
    Generales (&gt;8%): <strong>{{ (int) ($totales['general'] ?? 0) }}</strong>
    · monto {{ number_format((float) ($totales['monto_general'] ?? 0), 2, ',', '.') }}
</p>

<p style="margin:0 0 16px 0;">
    Adjunto el PDF con las dos secciones (listas 1/8 y &gt;8%).
</p>

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · reemplazo de p-controlnc
</p>
</body>
</html>
