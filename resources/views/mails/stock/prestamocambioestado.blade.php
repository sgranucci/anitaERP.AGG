<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Préstamo {{ $prestamo->codigo }} — {{ ucfirst($tipoCambio) }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222;">
    @if (! empty($textoIntro))
        <p>{{ $textoIntro }}</p>
    @endif

    @if ($tipoCambio === 'aprobado')
        <p>El préstamo <strong>{{ $prestamo->codigo }}</strong> fue <span style="color:#28a745;"><strong>APROBADO</strong></span>
            por el destinatario.</p>
    @elseif ($tipoCambio === 'rechazado')
        <p>El préstamo <strong>{{ $prestamo->codigo }}</strong> fue <span style="color:#dc3545;"><strong>RECHAZADO</strong></span>
            por el destinatario.</p>
    @else
        <p>El préstamo <strong>{{ $prestamo->codigo }}</strong> cambió de estado.</p>
    @endif

    @if (! empty($mensaje))
        <p><strong>Mensaje del destinatario:</strong> {{ $mensaje }}</p>
    @endif

    <h3 style="margin:18px 0 6px 0;">Datos del préstamo</h3>
    <ul style="line-height:1.5;">
        <li><strong>Código:</strong> {{ $prestamo->codigo }}</li>
        <li><strong>Depósito origen:</strong> {{ optional($prestamo->depositoOrigen)->nombre }}</li>
        <li><strong>Depósito destino:</strong> {{ optional($prestamo->depositoDestino)->nombre }}</li>
        <li><strong>Fecha del préstamo:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</li>
        <li><strong>Fecha prometida de devolución:</strong> {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') }}</li>
        @if ($prestamo->aprobador)
            <li><strong>Aprobador / receptor:</strong> {{ $prestamo->aprobador->nombre }}</li>
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
</body>
</html>
