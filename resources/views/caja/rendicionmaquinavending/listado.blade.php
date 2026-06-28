<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presentaciones rendición vending</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #ccc; padding: 3px; }
        table.data thead tr { background: #85C1E9; }
    </style>
</head>
<body>
    <h2>Presentaciones rendición vending en caja</h2>
    <p>Generado {{ date('d/m/Y H:i') }}</p>
    <table class="data">
        <thead>
            <tr>
                <th>ID</th><th>Código</th><th>Fecha</th><th>Empresa</th><th>Caja</th><th>Nº cierre</th><th>Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rendiciones as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->codigo }}</td>
                <td>{{ $r->fecharendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $r->empresa?->nombre }}</td>
                <td>{{ $r->caja?->nombre }}</td>
                <td>#{{ (int)($r->maquinavendingRendicion?->numero_cierre ?? 0) }}</td>
                <td>{{ number_format((float)$r->totalcobrado, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
