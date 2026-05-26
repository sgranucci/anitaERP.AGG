<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Préstamo {{ $prestamo->codigo }} — Recordatorio de devolución</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    @if ($vencido)
        <p style="font-size:15px;">El préstamo <strong>{{ $prestamo->codigo }}</strong>
            <span style="color:#dc3545;"><strong>está vencido</strong></span> y los materiales aún
            figuran a tu cargo.</p>
        @if (! empty($config->mail_texto_devolucion_vencida))
            <p>{{ $config->mail_texto_devolucion_vencida }}</p>
        @endif
    @else
        <p>Te recordamos que tenés materiales pendientes de devolución del préstamo
            <strong>{{ $prestamo->codigo }}</strong>.</p>
        @if (! empty($config->mail_texto_recordatorio))
            <p>{{ $config->mail_texto_recordatorio }}</p>
        @endif
    @endif

    <h3 style="margin:18px 0 6px 0;">Datos del préstamo</h3>
    <ul style="line-height:1.5;">
        <li><strong>Código:</strong> {{ $prestamo->codigo }}</li>
        <li><strong>Solicitante (depósito origen):</strong> {{ optional($prestamo->solicitante)->nombre }} — {{ optional($prestamo->depositoOrigen)->nombre }}</li>
        <li><strong>Receptor (depósito destino):</strong> {{ optional($prestamo->depositoDestino)->nombre }}</li>
        <li><strong>Fecha del préstamo:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</li>
        <li><strong>Fecha prometida de devolución:</strong>
            <span @if ($vencido) style="color:#dc3545; font-weight:bold;" @endif>
                {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') }}
            </span>
        </li>
    </ul>

    <h3 style="margin:18px 0 6px 0;">Ítems pendientes</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <thead style="background:#f0f0f0;">
            <tr>
                <th align="left">SKU</th>
                <th align="left">Artículo</th>
                <th align="right">Solicitado</th>
                <th align="right">Devuelto</th>
                <th align="right">Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestamo->items as $item)
                @php
                    $pendiente = max(0, (float) $item->cantidad - (float) $item->cantidad_devuelta);
                @endphp
                @if ($pendiente > 0)
                    <tr>
                        <td>{{ optional($item->articulos)->sku }}</td>
                        <td>{{ optional($item->articulos)->descripcion }}</td>
                        <td align="right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                        <td align="right">{{ rtrim(rtrim(number_format((float) $item->cantidad_devuelta, 6, '.', ''), '0'), '.') }}</td>
                        <td align="right"><strong>{{ rtrim(rtrim(number_format($pendiente, 6, '.', ''), '0'), '.') }}</strong></td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <p style="color:#888; font-size:11px; margin-top:24px;">
        Este es un recordatorio automático generado por el módulo de préstamos del ERP.
    </p>
</body>
</html>
