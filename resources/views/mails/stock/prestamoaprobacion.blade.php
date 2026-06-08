<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Préstamo {{ $prestamo->codigo }} — Pendiente de aprobación</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    <p>Hola <strong>{{ $destinatario->nombre }}</strong>,</p>

    @if (! empty($textoIntro))
        <p>{{ $textoIntro }}</p>
    @elseif (! empty($config->mail_texto_aprobacion))
        <p>{{ $config->mail_texto_aprobacion }}</p>
    @else
        <p>Te están enviando un préstamo de materiales hacia tu depósito. Por favor revisalo y, según corresponda,
            <strong>aprobalo</strong> para que el sistema confirme el ingreso al stock o <strong>rechazalo</strong> si no
            corresponde.</p>
    @endif

    <h3 style="margin:18px 0 6px 0;">Datos del préstamo</h3>
    <ul style="line-height:1.5;">
        <li><strong>Código:</strong> {{ $prestamo->codigo }}</li>
        <li><strong>Solicitante:</strong> {{ optional($prestamo->solicitante)->nombre }}</li>
        <li><strong>Depósito origen:</strong> {{ optional($prestamo->depositoOrigen)->nombre }}</li>
        <li><strong>Depósito destino:</strong> {{ optional($prestamo->depositoDestino)->nombre }}</li>
        <li><strong>Fecha del préstamo:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</li>
        <li><strong>Fecha prometida de devolución:</strong> {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') }}</li>
        @if (! empty($prestamo->observaciones))
            <li><strong>Observaciones:</strong> {{ $prestamo->observaciones }}</li>
        @endif
    </ul>

    <h3 style="margin:18px 0 6px 0;">Ítems</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
        <thead style="background:#f0f0f0;">
            <tr>
                <th align="left">SKU</th>
                <th align="left">Artículo</th>
                <th align="right">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prestamo->items as $item)
                <tr>
                    <td>{{ optional($item->articulos)->sku }}</td>
                    <td>{{ optional($item->articulos)->descripcion }}</td>
                    <td align="right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:18px;">
        <a href="{{ $links['aprobar'] }}" style="background:#28a745; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px; margin-right:8px;">Aprobar recepción</a>
        <a href="{{ $links['rechazar'] }}" style="background:#dc3545; color:#fff; padding:10px 16px; text-decoration:none; border-radius:4px;">Rechazar</a>
    </p>

    <p style="margin-top:8px;">
        ¿Querés ver el detalle completo antes? <a href="{{ $links['visualizar'] }}">Visualizar préstamo</a>
    </p>

    <p style="color:#888; font-size:11px; margin-top:24px;">
        Este correo fue generado automáticamente por el sistema. Si no esperabas recibirlo podés ignorarlo.
    </p>
</body>
</html>
