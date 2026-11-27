<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablero de compras</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $ok = (bool) ($datos['ok'] ?? false);
    $periodo = trim((string) ($datos['periodo'] ?? ''));
    $subtitulo = trim((string) ($datos['subtitulo'] ?? ''));
    $lineas = (int) ($datos['lineas'] ?? 0);
    $mensaje = trim((string) ($datos['mensaje'] ?? ''));
    $url = trim((string) ($datos['url_descarga'] ?? ''));
    $usuario = trim((string) ($datos['usuario_nombre'] ?? ''));
    $colorFondo = $ok ? '#e8f6ef' : '#fdecea';
    $colorBorde = $ok ? '#1E8449' : '#C0392B';
@endphp

<h2 style="margin:0 0 8px 0;">{{ $ok ? 'PDF del tablero de compras listo' : 'PDF del tablero de compras: error' }}</h2>

@if ($usuario !== '')
    <p style="margin:0 0 12px 0; color:#555;">Hola {{ $usuario }},</p>
@endif

<p style="margin:0 0 12px 0;">
    @if ($ok)
        Terminó el PDF del detalle de líneas de OC que pediste desde el tablero de compras
        (tenía muchas líneas, por eso se armó en segundo plano).
    @else
        No se pudo completar el PDF del detalle de líneas de OC del tablero de compras.
    @endif
</p>

<table style="border-collapse:collapse; margin:0 0 16px 0;">
    @if ($periodo !== '')
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Período</td>
            <td style="padding:4px 0;"><strong>{{ $periodo }}</strong></td>
        </tr>
    @endif
    @if ($subtitulo !== '')
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Filtros</td>
            <td style="padding:4px 0;">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ($ok && $lineas > 0)
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Líneas</td>
            <td style="padding:4px 0;">{{ number_format($lineas, 0, ',', '.') }}</td>
        </tr>
    @endif
</table>

@if ($mensaje !== '')
    <p style="padding:10px; background:{{ $colorFondo }}; border-left:4px solid {{ $colorBorde }}; margin:0 0 16px 0;">
        {{ $mensaje }}
    </p>
@endif

@if ($ok && $url !== '')
    <p style="margin:0 0 8px 0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 16px; background:#2471A3; color:#fff; text-decoration:none; border-radius:4px;">
            Descargar PDF
        </a>
    </p>
    <p style="margin:0; color:#777; font-size:12px;">
        Si el botón no funciona, copiá este enlace:<br>
        <a href="{{ $url }}">{{ $url }}</a>
    </p>
@endif

<p style="margin:24px 0 0 0; color:#888; font-size:12px;">
    {{ config('app.name', 'anitaERP') }} · generado {{ date('d/m/Y H:i') }}
</p>
</body>
</html>
