<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facturación Local</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 14px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 4px; }
        table.data td { border: 1px solid #cccccc; padding: 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>Facturación Local</h1>
    <p>Generado {{ now()->format('d/m/Y H:i') }} · Período {{ $desde }} — {{ $hasta }} · {{ $filas->count() }} registros</p>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Local</th>
                <th>Venta</th>
                <th>NC</th>
                <th>Total</th>
                <th>CAE</th>
                <th>Regalo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $e)
            <tr>
                <td>{{ $e->id }}</td>
                <td>{{ optional($e->created_at)->format('d/m/Y H:i') }}</td>
                <td>{{ $e->localVenta->codigo ?? '' }}</td>
                <td>{{ $e->venta->codigo ?? '' }}</td>
                <td>{{ $e->ventaNc->codigo ?? '' }}</td>
                <td>{{ number_format((float) ($e->venta->total ?? 0), 2, ',', '.') }}</td>
                <td>{{ $e->venta->cae ?? '' }}</td>
                <td>{{ $e->es_ticket_regalo ? 'Sí' : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
