<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flash Report AGG</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 4px 0;">Flash Report AGG</h2>
<p style="margin:0 0 16px 0; color:#555;">
    {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
    · Envío automático <strong>{{ $suscripcion->nombre }}</strong>
    · Generado el {{ now()->format('d/m/Y H:i') }}
</p>

@if (trim((string) ($suscripcion->mensaje ?? '')) !== '')
    <p style="padding:10px; background:#f4f6f7; border-left:4px solid #85C1E9; margin:0 0 16px 0;">
        {{ $suscripcion->mensaje }}
    </p>
@endif

@if (!empty($archivo['empresas']))
    <p style="margin:0 0 12px 0;">
        Empresas: {{ implode(', ', $archivo['empresas']) }}
        @if (!empty($archivo['dias']))
            · {{ $archivo['dias'] }} día(s) con flash
        @endif
    </p>
@endif

<p style="margin:0 0 12px 0;">
    Adjunto: <strong>{{ $archivo['nombre'] ?? 'Flash Report AGG.xlsx' }}</strong>
</p>

<p style="margin:20px 0 0 0; color:#777; font-size:12px;">
    Mail automático de {{ config('app.name', 'anitaERP') }}. Para dejar de recibirlo o cambiar el día,
    editá el envío «{{ $suscripcion->nombre }}» en Caja → Flash → Flash Report AGG.
</p>
</body>
</html>
