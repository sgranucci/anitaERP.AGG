<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de notas CRM</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditoría semanal de notas CRM</h2>
<p style="margin:0 0 16px 0;">
    Período:
    <strong>{{ $fechaDesde }}</strong>
    →
    <strong>{{ $fechaHasta }}</strong>
</p>

<p style="margin:0 0 12px 0;">
    Notas de todos los vendedores en el período:
    <strong>{{ $totalNotas }}</strong>.
</p>

@if ($totalNotas > 0)
    <p style="margin:0 0 16px 0;">
        Adjunto el PDF con el detalle (fecha, vendedor, empresa/cuenta, asunto y nota).
    </p>
@else
    <p style="margin:0 0 16px 0; color:#666;">
        No hubo notas cargadas en el período. Se adjunta el PDF vacío de control.
    </p>
@endif

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }}
</p>
</body>
</html>
