@php
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Marketplaces</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc; text-align: left; padding: 3px 4px; vertical-align: top;
            word-wrap: break-word; font-size: 7px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas { background-color: #85C1E9; }
        table.data thead tr.columnas > th { font-size: 7px; font-weight: bold; color: #17202A; background-color: #85C1E9; }
    </style>
</head>
<body>
    <table class="data">
        @include('ventas.facturacion_local.marketplace.partials.tabla_datos', [
            'presentacion' => 'pdf',
            'datas' => $datas,
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas,
        ])
    </table>
</body>
</html>
