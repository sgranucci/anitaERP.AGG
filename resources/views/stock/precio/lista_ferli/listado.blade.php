<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Lista de precios' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        table.data thead tr.columnas > th { background: #85C1E9; color: #17202A; font-size: 7px; }
        table.data tbody tr:nth-child(even) { background: #f5f5f5; }
        .text-right { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    @include('stock.precio.lista_ferli.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'listas' => $listas ?? [],
        'titulo' => $titulo ?? 'Lista de precios',
        'subtitulo' => $subtitulo ?? '',
        'logosCabecera' => $logosCabecera ?? [],
        'pdf' => true,
    ])
</body>
</html>
