<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digest de aprobaciones</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2d3d; line-height: 1.45;">
    <p>Hola {{ $nombreUsuario }},</p>
    <p>
        Al {{ $fecha->format('d/m/Y') }} tenés
        <strong>{{ $total }}</strong> pendiente{{ $total === 1 ? '' : 's' }}
        de aprobación en Anita
        @if ($urgentes > 0)
            ({{ $urgentes }} con más de 5 días)
        @endif
        .
    </p>

    <p>
        <a href="{{ $linkBandeja }}"
           style="display:inline-block;padding:10px 16px;background:#0f3d56;color:#fff;text-decoration:none;font-weight:700;border-radius:3px;">
            Abrir mi bandeja en Anita
        </a>
    </p>

    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;border-color:#dbe2ea;width:100%;max-width:760px;font-size:14px;">
        <thead style="background:#f4f6f9;">
            <tr>
                <th align="left">Fuente</th>
                <th align="left">Tipo</th>
                <th align="left">Documento</th>
                <th align="right">Monto</th>
                <th align="center">Días</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['fuente_label'] ?? $item['fuente'] ?? '' }}</td>
                    <td>{{ $item['tipo'] ?? '' }}</td>
                    <td>{{ $item['numero'] ?? '' }}</td>
                    <td align="right">
                        @if (($item['monto'] ?? 0) > 0)
                            {{ number_format((float) $item['monto'], 2, ',', '.') }}
                            {{ $item['moneda_abrev'] ?? '' }}
                        @else
                            —
                        @endif
                    </td>
                    <td align="center">{{ $item['dias_pendiente'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($total > count($items))
        <p style="font-size:13px;color:#5d6d7e;">
            Se listan los primeros {{ count($items) }} de {{ $total }}. El resto está en la bandeja.
        </p>
    @endif

    <p style="font-size:13px;color:#5d6d7e;margin-top:1.25rem;">
        Este es un resumen diario de todas tus pendientes. Los mails individuales siguen activos.
    </p>
</body>
</html>
