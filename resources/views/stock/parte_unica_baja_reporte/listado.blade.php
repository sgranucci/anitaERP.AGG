<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #444; margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data thead th { background-color: #85C1E9; color: #17202A; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <h1>{{ $titulo ?? 'Consulta NPU' }}</h1>
    <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
    @if (!empty($subtitulo))
        <div class="meta">{{ $subtitulo }}</div>
    @endif
    @if (!empty($totales))
        <div class="meta">
            Registros: {{ $totales['total_registros'] ?? 0 }}
            &middot; Baja: {{ $totales['total_baja'] ?? 0 }}
            &middot; Activos: {{ $totales['total_activos'] ?? 0 }}
        </div>
    @endif

    @include('stock.parte_unica_baja_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'puede_ver_articulo' => false,
        'puede_ver_movimiento' => false,
    ])
</body>
</html>
