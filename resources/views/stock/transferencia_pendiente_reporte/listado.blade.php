<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #333; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if (! empty($subtitulo))
            · {{ $subtitulo }}
        @endif
        · Transferencias: {{ $totales['total'] ?? count($filas) }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Fecha</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>Tipo</th>
                <th>Ítems</th>
                <th>Remitente</th>
                <th>Destinatario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $t)
                <tr>
                    <td>{{ $t->codigo }}</td>
                    <td>{{ $t->fecha?->format('d/m/Y') }}</td>
                    <td>
                        @if ($t->bien_uso_origen_id)
                            {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoOrigen) }}
                        @else
                            {{ optional($t->depositoOrigen)->nombre }}
                        @endif
                    </td>
                    <td>
                        @if ($t->bien_uso_destino_id)
                            {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoDestino) }}
                        @else
                            {{ optional($t->depositoDestino)->nombre }}
                        @endif
                    </td>
                    <td>{{ optional($t->tipotransaccion_stock)->nombre }}</td>
                    <td>{{ $t->articulos->count() }}</td>
                    <td>{{ optional($t->usuarioOrigen)->nombre }}</td>
                    <td>{{ optional($t->usuarioDestino)->nombre }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
