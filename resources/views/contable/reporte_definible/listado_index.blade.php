<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reportes definibles</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { font-size: 14px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #ccc; padding: 3px; }
        table.data td { border: 1px solid #ccc; padding: 2px 3px; }
        table.data tr:nth-child(even) { background: #f5f5f5; }
    </style>
</head>
<body>
<h1>Reportes contables definibles</h1>
<div>Generado {{ date('d/m/Y H:i') }} · {{ $filas->count() }} registros</div>
<table class="data">
    <thead>
        <tr>
            <th>Código</th>
            <th>Nombre</th>
            <th>Título 1</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Activo</th>
            <th>Rubros</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $item)
            <tr>
                <td>{{ $item->codigo }}</td>
                <td>{{ $item->nombre }}</td>
                <td>{{ $item->titulo1 }}</td>
                <td>{{ $item->tipo }}</td>
                <td>{{ $item->origen }}</td>
                <td>{{ $item->activo ? 'Sí' : 'No' }}</td>
                <td>{{ $item->rubros_count ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
