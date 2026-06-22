<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transferencia {{ $transferencia->codigo }}</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; padding:24px; color:#333; }
        .card { max-width:720px; margin:0 auto; background:#fff; border-radius:6px; padding:24px; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { border:1px solid #ddd; padding:6px 8px; text-align:left; }
        th { background:#85C1E9; color:#17202A; }
        .actions { margin-top:16px; }
        .btn { display:inline-block; padding:10px 16px; margin-right:8px; border-radius:4px; text-decoration:none; color:#fff; border:0; cursor:pointer; }
        .btn-ok { background:#28a745; }
        .btn-danger { background:#dc3545; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Transferencia {{ $transferencia->codigo }}</h1>
        <p>
            <strong>Origen:</strong>
            @if ($transferencia->bien_uso_origen_id)
                {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($transferencia->bienUsoOrigen) }}
            @else
                {{ optional($transferencia->depositoOrigen)->nombre }}
            @endif
            <br>
            <strong>Destino:</strong>
            @if ($transferencia->bien_uso_destino_id)
                {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($transferencia->bienUsoDestino) }}
            @else
                {{ optional($transferencia->depositoDestino)->nombre }}
            @endif
            <br>
            <strong>Fecha:</strong> {{ $transferencia->fecha?->format('d/m/Y') }}
        </p>
        <table>
            <thead>
                <tr>
                    <th>SKU origen</th>
                    <th>Descripción</th>
                    <th>Cant. origen</th>
                    <th>SKU destino</th>
                    <th>Cant. destino</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transferencia->articulos as $linea)
                    <tr>
                        <td>{{ optional($linea->articuloOrigen)->sku }}</td>
                        <td>{{ optional($linea->articuloOrigen)->descripcion }}</td>
                        <td>{{ number_format((float) $linea->cantidad_origen, 4, ',', '.') }}</td>
                        <td>
                            @if ($linea->fl_conversion_formula)
                                {{ optional($linea->articuloDestino)->sku }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($linea->fl_conversion_formula)
                                {{ number_format((float) $linea->cantidad_destino, 4, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="actions">
            <form method="get" action="{{ url('stock/transferencia-mercaderia/publico/'.$token.'/aprobar') }}" style="display:inline">
                <button type="submit" class="btn btn-ok">Aprobar recepción</button>
            </form>
            <form method="post" action="{{ url('stock/transferencia-mercaderia/publico/'.$token.'/rechazar') }}" style="display:inline">
                @csrf
                <input type="hidden" name="motivo" value="Rechazado desde enlace público">
                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Rechazar esta transferencia?');">Rechazar</button>
            </form>
        </div>
    </div>
</body>
</html>
