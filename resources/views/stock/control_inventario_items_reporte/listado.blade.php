<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Control de inventario de items' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #333; }
        h1 { font-size: 13px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #666; margin-bottom: 6px; }
        .aviso { font-size: 7px; color: #856404; margin-bottom: 4px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 2px 3px; }
        table.data th { background: #85C1E9; color: #17202A; }
        .text-right { text-align: right; }
        .warn { background: #fff3cd; }
    </style>
</head>
<body>
    <h1>{{ $titulo ?? 'Control de inventario de items' }}</h1>
    @if (! empty($subtitulo))
        <div class="meta">{{ $subtitulo }}</div>
    @endif
    @foreach ($avisos ?? [] as $aviso)
        <div class="aviso">{{ $aviso }}</div>
    @endforeach
    @include('stock.control_inventario_items_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'totales' => $totales ?? [],
        'puede_ver_articulo' => false,
        'solo_thead_tbody' => false,
    ])
</body>
</html>
