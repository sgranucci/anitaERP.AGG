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
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="meta">
        Generado: {{ now()->format('d/m/Y H:i') }}
        @if (! empty($subtitulo))
            · {{ $subtitulo }}
        @endif
        · Registros: {{ $totales['total_registros'] ?? count($filas) }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Bien</th>
                <th>Efecto</th>
                <th>SKU</th>
                <th>Artículo</th>
                <th class="text-right">Cant.</th>
                <th>Tipo</th>
                <th>Mov.</th>
                <th>Transfer.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $row)
                @php $cantidad = (float) ($row->cantidad ?? 0); @endphp
                <tr>
                    <td>{{ $row->fecha ? \Carbon\Carbon::parse($row->fecha)->format('d/m/Y') : '' }}</td>
                    <td>{{ $row->bien_hostname }}</td>
                    <td>{{ \App\Support\Stock\BienUsoAsignacionSupport::etiquetaEfecto($cantidad) }}</td>
                    <td>{{ $row->sku }}</td>
                    <td>{{ $row->articulo_descripcion }}</td>
                    <td class="text-right">{{ number_format(abs($cantidad), 4, ',', '.') }}</td>
                    <td>{{ $row->tipo_transaccion }}</td>
                    <td>{{ $row->movimiento_codigo }}</td>
                    <td>{{ $row->transferencia_codigo ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
