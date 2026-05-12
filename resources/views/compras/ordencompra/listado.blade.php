<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado órdenes de compra</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; }
        th { background: #eee; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h2>Órdenes de compra</h2>
    <table>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fecha</th>
                <th>Empresa</th>
                <th>Centro costo</th>
                <th>Proveedor</th>
                <th>Sector</th>
                <th>Estado</th>
                <th class="num">Σ ítems</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ordencompra as $row)
                <tr>
                    <td>{{ $row->numeroordencompra }}</td>
                    <td>{{ date('d/m/Y', strtotime($row->fecha)) }}</td>
                    <td>{{ $row->nombreempresa }}</td>
                    <td>{{ $row->nombrecentrocosto }}</td>
                    <td>{{ $row->nombreproveedor }}</td>
                    <td>{{ $row->nombresector ?? '' }}</td>
                    <td>{{ $row->estadoordencompra }}</td>
                    <td class="num">{{ number_format((float) ($row->monto_lineas ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
