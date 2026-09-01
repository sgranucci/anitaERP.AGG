<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salida {{ $prestamo->codigo }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:30px; color:#333; }
        .card { max-width:780px; margin:0 auto; background:#fff; border-radius:6px; padding:24px; box-shadow:0 1px 6px rgba(0,0,0,.06); }
        h1 { font-size:22px; margin-top:0; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border:1px solid #ddd; padding:6px; text-align:left; }
        th { background:#85C1E9; color:#17202A; }
        .num { text-align:right; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Salida de bienes {{ $prestamo->codigo }}</h1>
        <p><strong>Tipo:</strong> {{ $prestamo->etiquetaTipo() }}</p>
        <p><strong>Solicitante:</strong> {{ optional($prestamo->solicitante)->nombre }}</p>
        <p><strong>Depósito origen:</strong> {{ optional($prestamo->depositoOrigen)->nombre }}</p>
        <p><strong>Destinatario:</strong> {{ $prestamo->etiquetaDestinatario() }}</p>
        <p><strong>Fecha salida:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</p>
        <p><strong>Devolución prometida:</strong> {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') ?: '—' }}</p>
        @if (! empty($prestamo->observaciones))
            <p><strong>Observaciones:</strong> {{ $prestamo->observaciones }}</p>
        @endif

        <h3>Ítems</h3>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Descripción</th>
                    <th>Serie</th>
                    <th class="num">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prestamo->items as $item)
                    <tr>
                        <td>{{ optional($item->articulos)->sku ?: '—' }}</td>
                        <td>{{ $item->descripcionMostrada() }}</td>
                        <td>{{ $item->nro_serie ?: '—' }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
