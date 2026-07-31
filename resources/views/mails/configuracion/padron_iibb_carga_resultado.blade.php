<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga padrón IIBB</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; line-height:1.5;">
@php
    $ok = (bool) ($resultado['ok'] ?? false);
    $color = $ok ? '#1e7e34' : '#721c24';
    $stats = is_array($resultado['stats'] ?? null) ? $resultado['stats'] : [];
@endphp

<h2 style="margin:0 0 12px 0; color:{{ $color }};">
    @if ($ok)
        Carga de padrón finalizada
    @else
        Error en carga de padrón
    @endif
</h2>

<p style="margin:0 0 8px 0;">
    <strong>Origen:</strong> {{ $resultado['origen'] ?? '' }}<br>
    @if (! empty($resultado['archivo']))
        <strong>Archivo:</strong> {{ $resultado['archivo'] }}<br>
    @endif
    <strong>Mensaje:</strong> {{ $resultado['mensaje'] ?? '' }}
</p>

@if (! empty($resultado['error']))
    <div style="background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:12px 14px; border-radius:4px; margin:12px 0;">
        {{ $resultado['error'] }}
    </div>
@endif

@if ($stats !== [])
    <h3 style="margin:16px 0 8px 0; font-size:1rem;">Detalle</h3>
    <table style="border-collapse:collapse; width:100%; max-width:520px;">
        @foreach ($stats as $k => $v)
            <tr>
                <td style="border:1px solid #ccc; padding:6px 8px; background:#f5f5f5;">{{ $k }}</td>
                <td style="border:1px solid #ccc; padding:6px 8px;">
                    @if (is_array($v))
                        <pre style="margin:0; font-size:12px;">{{ json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                    @else
                        {{ $v }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
@endif

<p style="margin:16px 0 0 0; color:#666; font-size:12px;">
    Anita ERP — notificación automática de padrones IIBB
</p>
</body>
</html>
