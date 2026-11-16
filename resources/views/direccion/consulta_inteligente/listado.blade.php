<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas th {
            background: #85C1E9;
            color: #17202A;
            font-weight: bold;
            border: 1px solid #cccccc;
            padding: 4px 5px;
            text-align: left;
        }
        table.data tbody td {
            border: 1px solid #cccccc;
            padding: 3px 5px;
        }
        table.data tbody tr:nth-child(even) td { background: #f5f5f5; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo ?? '',
            'colspan' => max(1, count($columnas)),
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
        <tr class="columnas">
            @foreach ($columnas as $key)
                <th>{{ $etiquetas[$key] ?? $key }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($matriz as $linea)
            <tr>
                @foreach ($columnas as $key)
                    <td>{{ $linea[$key] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
