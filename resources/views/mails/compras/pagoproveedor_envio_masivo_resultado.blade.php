<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envío masivo de OP</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $ok = (bool) ($datos['ok'] ?? false);
    $okProceso = (bool) ($datos['ok_proceso'] ?? false);
    $enviadas = (int) ($datos['enviadas'] ?? 0);
    $fallidas = (int) ($datos['fallidas'] ?? 0);
    $total = (int) ($datos['total'] ?? 0);
    $usuario = trim((string) ($datos['usuario_nombre'] ?? ''));
    $fechaDesde = trim((string) ($datos['fecha_desde'] ?? ''));
    $fechaHasta = trim((string) ($datos['fecha_hasta'] ?? ''));
    $medio = trim((string) ($datos['medio'] ?? 'ambas'));
    $errorFatal = trim((string) ($datos['error_fatal'] ?? ''));
    $errores = $datos['errores'] ?? [];
    $detalle = $datos['detalle'] ?? [];
@endphp

<h2 style="margin:0 0 8px 0;">
    @if ($ok)
        Envío de OP a proveedores listo
    @elseif ($enviadas > 0)
        Envío de OP a proveedores parcial
    @else
        Envío de OP a proveedores — con errores
    @endif
</h2>

@if ($usuario !== '')
    <p style="margin:0 0 12px 0; color:#555;">Hola {{ $usuario }},</p>
@endif

<p style="margin:0 0 12px 0;">
    Terminó el envío masivo de órdenes de pago en segundo plano.
</p>

<table style="border-collapse:collapse; margin:0 0 16px 0;">
    @if ($fechaDesde !== '' || $fechaHasta !== '')
        <tr>
            <td style="padding:4px 12px 4px 0; color:#555;">Período</td>
            <td style="padding:4px 0;">
                <strong>{{ $fechaDesde }}</strong>
                @if ($fechaHasta !== '' && $fechaHasta !== $fechaDesde)
                    — <strong>{{ $fechaHasta }}</strong>
                @endif
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding:4px 12px 4px 0; color:#555;">Medio</td>
        <td style="padding:4px 0;">{{ $medio }}</td>
    </tr>
    <tr>
        <td style="padding:4px 12px 4px 0; color:#555;">Seleccionadas</td>
        <td style="padding:4px 0;">{{ $total }}</td>
    </tr>
    <tr>
        <td style="padding:4px 12px 4px 0; color:#555;">Enviadas</td>
        <td style="padding:4px 0;"><strong>{{ $enviadas }}</strong></td>
    </tr>
    <tr>
        <td style="padding:4px 12px 4px 0; color:#555;">Con error</td>
        <td style="padding:4px 0;">{{ $fallidas }}</td>
    </tr>
</table>

@if ($errorFatal !== '')
    <p style="padding:10px; background:#fdecea; border-left:4px solid #C0392B; margin:0 0 16px 0;">
        Error del proceso: {{ $errorFatal }}
    </p>
@elseif ($ok)
    <p style="padding:10px; background:#e8f6ef; border-left:4px solid #1E8449; margin:0 0 16px 0;">
        Se enviaron todos los correos seleccionados.
    </p>
@elseif ($enviadas > 0)
    <p style="padding:10px; background:#fef9e7; border-left:4px solid #F39C12; margin:0 0 16px 0;">
        Parte de las OP se enviaron; revisá el detalle de errores.
    </p>
@elseif (! $okProceso)
    <p style="padding:10px; background:#fdecea; border-left:4px solid #C0392B; margin:0 0 16px 0;">
        El proceso no pudo completarse.
    </p>
@endif

@if (!empty($errores))
    <h3 style="margin:16px 0 8px 0; font-size:15px;">No enviadas</h3>
    <table style="border-collapse:collapse; width:100%; margin:0 0 16px 0;">
        <thead>
            <tr style="background:#85C1E9; color:#17202A;">
                <th style="text-align:left; padding:6px 8px;">OP</th>
                <th style="text-align:left; padding:6px 8px;">Motivo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($errores as $error)
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:6px 8px;">{{ $error['etiqueta'] ?? '' }}</td>
                    <td style="padding:6px 8px;">{{ $error['mensaje'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (!empty($detalle))
    <h3 style="margin:16px 0 8px 0; font-size:15px;">Detalle</h3>
    <table style="border-collapse:collapse; width:100%; margin:0 0 16px 0;">
        <thead>
            <tr style="background:#85C1E9; color:#17202A;">
                <th style="text-align:left; padding:6px 8px;">OP</th>
                <th style="text-align:left; padding:6px 8px;">Resultado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detalle as $item)
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:6px 8px;">{{ $item['etiqueta'] ?? '' }}</td>
                    <td style="padding:6px 8px;">
                        @if (!empty($item['ok']))
                            OK — {{ $item['mensaje'] ?? '' }}
                        @else
                            Error — {{ $item['mensaje'] ?? '' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p style="margin:24px 0 0 0; color:#888; font-size:12px;">
    {{ config('app.name', 'anitaERP') }} · generado {{ date('d/m/Y H:i') }}
</p>
</body>
</html>
