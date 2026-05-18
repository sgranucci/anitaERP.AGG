<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de precios</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; }
        h2 { font-size: 12px; margin: 0 0 8px 0; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 4px; text-align: left; }
        table.data thead tr { background-color: #d4e6f1; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2>Precios vigentes al {{ date('d/m/Y', strtotime($fechaReferencia)) }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>SKU</th>
                <th>Descripción</th>
                <th>Lista de precios</th>
                <th>Fecha vigencia</th>
                <th>Moneda</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Precio anterior</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($precios as $precio)
            <tr>
                <td>{{ $precio->id }}</td>
                <td>{{ $precio->sku }}</td>
                <td>{{ $precio->articulo_descripcion }}</td>
                <td>{{ $precio->listaprecio_nombre }}</td>
                <td>{{ $precio->fechavigencia ? date('d/m/Y', strtotime($precio->fechavigencia)) : '' }}</td>
                <td>{{ $precio->moneda_nombre }}</td>
                <td class="text-right">{{ number_format((float) $precio->precio, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $precio->precioanterior, 2, ',', '.') }}</td>
                <td>{{ $precio->usuario_nombre }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
