<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        .data { width: 100%; border-collapse: collapse; }
        .data th, .data td { border: 1px solid #cccccc; padding: 3px; }
        .data thead th { background: #85C1E9; }
        .data tbody tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
    <h3>Listado de requisiciones de sala</h3>
    <p>Generado {{ date('d/m/Y H:i') }} — {{ $filas->count() }} registros</p>
    <table class="data">
        <thead>
            <tr>
                <th>Número</th><th>Fecha</th><th>Empresa</th><th>Centro costo</th>
                <th>Depósito</th><th>Zona</th><th>Prioridad</th><th>Estado</th><th>Artículos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $f)
            <tr>
                <td>{{ $f->numerorequisicion }}</td>
                <td>{{ date('d/m/Y', strtotime($f->fecha)) }}</td>
                <td>{{ $f->nombreempresa }}</td>
                <td>{{ $f->nombrecentrocosto }}</td>
                <td>{{ $f->nombredeposito }}</td>
                <td>{{ $f->nombrezona }}</td>
                <td>{{ $f->nombreprioridad }}</td>
                <td>{{ $f->estado }}</td>
                <td>
                    @foreach ($f->requisicion_sala_articulos as $item)
                        {{ $item->articulos->sku ?? '' }}-{{ $item->articulos->descripcion ?? '' }} ({{ $item->cantidad }})<br>
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
