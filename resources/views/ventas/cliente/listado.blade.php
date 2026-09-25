<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Clientes</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; }
        h2 { margin: 0 0 8px; font-size: 16px; }
    </style>
</head>
<body>
    <h2>Listado de clientes</h2>
    <div style="font-size: 8px; margin-bottom: 8px;">Generado {{ date('d/m/Y H:i') }}</div>
    <table class="data">
        @include('ventas.cliente.partials.tabla_listado_export', [
            'clientes' => $clientes,
            'columnasVisibles' => $columnasVisibles ?? null,
            'etiquetasColumnas' => $etiquetasColumnas ?? [],
        ])
    </table>
</body>
</html>
