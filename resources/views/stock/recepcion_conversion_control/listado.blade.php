<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo ?? 'Control conversión recepción' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th {
            background: #85C1E9;
            color: #17202A;
            border: 1px solid #cccccc;
            padding: 3px 4px;
            font-weight: bold;
        }
        table.data tbody td {
            border: 1px solid #cccccc;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #f5f5f5; }
        .rcc-header-empresa td { background: #1B4F72; color: #fff; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@include('stock.recepcion_conversion_control.partials.tabla_datos', [
    'filas' => $filas ?? [],
    'totales' => $totales ?? [],
    'titulo' => $titulo ?? 'Control conversión recepción',
    'subtitulo' => $subtitulo ?? '',
    'pdf' => true,
    'puede_ver_recepcion' => false,
    'puede_ver_articulo' => false,
    'puede_ver_proveedor' => false,
])
</body>
</html>
