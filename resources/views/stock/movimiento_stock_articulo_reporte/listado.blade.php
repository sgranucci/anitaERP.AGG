<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Movimientos de stock' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 2px 3px; vertical-align: top; }
        table.data thead tr.columnas > th { background: #85C1E9; color: #17202A; font-size: 7px; }
        .text-right { text-align: right; white-space: nowrap; }
        tr.salto-articulo { page-break-before: always; }
    </style>
</head>
<body>
    @include('stock.movimiento_stock_articulo_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'totales' => $totales ?? [],
        'titulo' => $titulo ?? 'Movimientos de stock',
        'subtitulo' => $subtitulo ?? '',
        'pdf' => true,
        'puede_ver_articulo' => false,
    ])
</body>
</html>
