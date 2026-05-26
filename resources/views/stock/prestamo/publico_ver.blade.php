<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préstamo {{ $prestamo->codigo }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:30px; color:#333; }
        .card { max-width:780px; margin:0 auto; background:#fff; border-radius:6px; padding:24px; box-shadow:0 1px 6px rgba(0,0,0,.06); }
        h1 { font-size:22px; margin-top:0; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { border:1px solid #ddd; padding:6px; text-align:left; }
        th { background:#f0f0f0; }
        .num { text-align:right; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Préstamo {{ $prestamo->codigo }}</h1>
        <p><strong>Solicitante:</strong> {{ optional($prestamo->solicitante)->nombre }}</p>
        <p><strong>Depósito origen:</strong> {{ optional($prestamo->depositoOrigen)->nombre }}</p>
        <p><strong>Depósito destino:</strong> {{ optional($prestamo->depositoDestino)->nombre }}</p>
        <p><strong>Fecha del préstamo:</strong> {{ optional($prestamo->fecha_prestamo)->format('d/m/Y') }}</p>
        <p><strong>Fecha prometida de devolución:</strong> {{ optional($prestamo->fecha_devolucion_prometida)->format('d/m/Y') }}</p>
        @if (! empty($prestamo->observaciones))
            <p><strong>Observaciones:</strong> {{ $prestamo->observaciones }}</p>
        @endif

        <h3>Ítems</h3>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Artículo</th>
                    <th class="num">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prestamo->items as $item)
                    <tr>
                        <td>{{ optional($item->articulos)->sku }}</td>
                        <td>{{ optional($item->articulos)->descripcion }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $item->cantidad, 6, '.', ''), '0'), '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
