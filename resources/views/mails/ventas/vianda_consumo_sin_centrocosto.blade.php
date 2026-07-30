<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vianda sin centro de costo</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px; line-height:1.45;">
@php
    $d = $datos ?? [];
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

<h2 style="margin:0 0 10px 0; color:#333;">Vianda marchada sin centro de costo</h2>

<p style="margin:0 0 14px 0; color:#b45309; font-weight:bold;">
    Un empleado retiró vianda y el consumo quedó sin centro de costo.
    La operación no se bloqueó; conviene asignar el C.C. en el ABM de usuarios de vianda.
</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; margin-bottom:16px;">
    <tr>
        <td style="background:#f5f5f5;"><strong>Empresa</strong></td>
        <td>{{ $d['empresa_nombre'] ?? '—' }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Código retiro</strong></td>
        <td>{{ $d['codigo_retiro'] ?? '—' }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Fecha / hora</strong></td>
        <td>{{ $d['fecha_hora'] ?? '—' }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Código usuario</strong></td>
        <td>{{ $d['codigo_usuario'] ?? '—' }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Empleado</strong></td>
        <td>{{ $d['nombre_usuario'] ?? '—' }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Ítems</strong></td>
        <td>{{ (int) ($d['cantidad_items'] ?? 0) }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Costo total</strong></td>
        <td>$ {{ $fmt($d['total_costo'] ?? 0) }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Venta total</strong></td>
        <td>$ {{ $fmt($d['total_venta'] ?? 0) }}</td>
    </tr>
    <tr>
        <td style="background:#f5f5f5;"><strong>Terminal</strong></td>
        <td>{{ $d['terminal'] ?? '—' }}</td>
    </tr>
</table>

@if (! empty($d['link_usuario']))
    <p style="margin: 16px 0;">
        <a href="{{ $d['link_usuario'] }}" style="background:#007bff; color:#fff; padding:10px 18px; text-decoration:none; border-radius:4px;">
            Abrir usuario de vianda
        </a>
    </p>
    <p style="font-size:12px; color:#666;">
        Si el botón no funciona, copiá este enlace:<br>
        <a href="{{ $d['link_usuario'] }}">{{ $d['link_usuario'] }}</a>
    </p>
@endif

<p style="color:#888; font-size:11px; margin-top:28px;">
    Correo generado automáticamente por Anita ERP.
</p>
</body>
</html>
