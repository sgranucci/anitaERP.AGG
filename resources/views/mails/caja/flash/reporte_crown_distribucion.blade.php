<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Flash Report Crown</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 4px 0;">Flash Report Crown</h2>
<p style="margin:0 0 16px 0; color:#555;">
    {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
    · Envío <strong>{{ $suscripcion->nombre }}</strong>
    · Generado el {{ now()->format('d/m/Y H:i') }}
</p>

@if (trim((string) ($suscripcion->mensaje ?? '')) !== '')
    <p style="padding:10px; background:#f4f6f7; border-left:4px solid #85C1E9; margin:0 0 16px 0;">
        {{ $suscripcion->mensaje }}
    </p>
@endif

@if (!empty($archivo['dias']))
    <p style="margin:0 0 12px 0;">{{ $archivo['dias'] }} día(s) con flash validado.</p>
@endif

<p style="margin:0; color:#555;">Adjunto: Excel completo (mesas, slots, F&amp;B, hotel, Combined vs año anterior).</p>
</body>
</html>
