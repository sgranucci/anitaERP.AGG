<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor plano</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $ok = (bool) ($datos['ok'] ?? false);
    $periodo = trim((string) ($datos['periodo'] ?? ''));
    $empresas = trim((string) ($datos['empresas'] ?? ''));
    $lineas = (int) ($datos['lineas'] ?? 0);
    $mensaje = trim((string) ($datos['mensaje'] ?? ''));
    $url = trim((string) ($datos['url_descarga'] ?? ''));
    $usuario = trim((string) ($datos['usuario_nombre'] ?? ''));
@endphp

<h2 style="margin:0 0 8px 0;">
    @if ($ok)
        Mayor plano listo
    @else
        Mayor plano — error
    @endif
</h2>

@if ($usuario !== '')
    <p style="margin:0 0 12px 0; color:#555;">Hola {{ $usuario }},</p>
@endif

@if ($ok)
    <p style="margin:0 0 12px 0;">
        Terminó el Excel plano del mayor analítico por cuenta (período largo: no se muestra en pantalla).
        El CSV incluye emisor, OC, proyecto CAPEX, qué se compró y números de factura.
    </p>
@else
    <p style="margin:0 0 12px 0;">
        No se pudo completar el mayor plano en segundo plano.
    </p>
@endif

<table style="border-collapse:collapse; margin:0 0 16px 0;">
    @if ($periodo !== '')
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Período</td>
            <td style="padding:4px 0;"><strong>{{ $periodo }}</strong></td>
        </tr>
    @endif
    @if ($empresas !== '')
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Empresas</td>
            <td style="padding:4px 0;">{{ $empresas }}</td>
        </tr>
    @endif
    @if ($ok && $lineas > 0)
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Movimientos</td>
            <td style="padding:4px 0;">{{ number_format($lineas, 0, ',', '.') }}</td>
        </tr>
    @endif
</table>

@if ($mensaje !== '')
    <p style="padding:10px; background:{{ $ok ? '#e8f6ef' : '#fdecea' }}; border-left:4px solid {{ $ok ? '#1E8449' : '#C0392B' }}; margin:0 0 16px 0;">
        {{ $mensaje }}
    </p>
@endif

@if ($ok && $url !== '')
    <p style="margin:0 0 8px 0;">
        <a href="{{ $url }}" style="display:inline-block; padding:10px 16px; background:#2471A3; color:#fff; text-decoration:none; border-radius:4px;">
            Descargar Excel plano (CSV)
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
