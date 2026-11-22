<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control AFIP pedidos</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fechaControl = (string) ($informe['fecha_control'] ?? '');
    $desde = (string) ($informe['fecha_desde'] ?? '');
    $hasta = (string) ($informe['fecha_hasta'] ?? '');
    $problemas = (int) ($informe['problemas'] ?? 0);
@endphp
<h2 style="margin:0 0 8px 0;">Clientes con problemas de AFIP</h2>
<p style="margin:0 0 12px 0;">
    Se informan los clientes con problemas de AFIP en control fecha
    <strong>{{ $fechaControl !== '' ? \Carbon\Carbon::parse($fechaControl)->format('d/m/Y') : '—' }}</strong>
    y movimientos de pedidos entre el
    <strong>{{ $desde !== '' ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '—' }}</strong>
    y el
    <strong>{{ $hasta !== '' ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : '—' }}</strong>.
</p>
<p style="margin:0 0 12px 0;">
    Clientes con problemas: <strong>{{ $problemas }}</strong>
</p>
@if (! ($informe['anita_ok'] ?? true))
    <p style="margin:0 0 12px 0; color:#c0392b;">
        Lectura Anita falló: {{ $informe['anita_error'] ?? '' }}
    </p>
@endif
@if ((int) ($informe['arca_errores_servicio'] ?? 0) > 0)
    <p style="margin:0 0 12px 0; color:#c0392b;">
        Consultas al padrón ARCA no disponibles: {{ (int) $informe['arca_errores_servicio'] }}
        (no se marcaron como problema fiscal).
    </p>
@endif
<p style="margin:0 0 16px 0;">
    Adjunto PDF y Excel con el detalle (código, cliente, CUIT, vendedor, motivo).
    Además se envió un aviso individual a cada vendedor con clientes afectados (si tiene email).
</p>
<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · {{ config('app.empresa') }} · reemplazo de l-pedrem -b
</p>
</body>
</html>
