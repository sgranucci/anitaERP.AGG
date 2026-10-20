<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Habilitar modificación de flash</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $flash = $solicitud->flash;
    $fecha = $flash?->fecha?->format('d/m/Y') ?? '';
    $empresa = (string) ($flash?->empresa?->nombre ?? '');
    $solicitante = trim((string) ($solicitud->solicitante?->nombre ?: $solicitud->solicitante?->usuario ?: ''));
@endphp
<p>Hola,</p>
<p>
    <strong>{{ $solicitante !== '' ? $solicitante : 'Un usuario' }}</strong>
    pide modificar el flash del <strong>{{ $fecha }}</strong>
    @if ($empresa !== '')
        de <strong>{{ $empresa }}</strong>
    @endif
    . Ese día tiene el tilde verde y ya tiene cierre contable de máquinas.
</p>
<p style="padding:10px; background:#f4f6f7; border-left:4px solid #85C1E9;">
    {{ $solicitud->motivo }}
</p>
<p>El enlace habilita esa modificación. No cambia los importes: quien pidió el cambio es quien puede guardarlo después.</p>
<p>
    <a href="{{ $urlHabilitar }}" style="display:inline-block; background:#1e8449; color:#fff; text-decoration:none; padding:8px 14px;">
        Habilitar modificación
    </a>
</p>
<p style="color:#666; font-size:12px;">Si el botón no abre, copie esta dirección:<br>{{ $urlHabilitar }}</p>
</body>
</html>
