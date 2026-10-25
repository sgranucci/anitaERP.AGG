<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead { display: table-header-group; }
        table.data thead tr.columnas > th { background-color: #85C1E9; color: #17202A; font-size: 7px; font-weight: bold; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => $titulo,
            'subtitulo' => $subtitulo ?? '',
            'colspan' => count($columnas),
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
        <tr class="columnas">
            @foreach ($columnas as $columna)
                <th>{{ $columna['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                @foreach ($fila as $i => $celda)
                    <td style="{{ !empty($columnas[$i]['numero']) ? 'text-align:right;' : '' }}">
                        @if (!empty($columnas[$i]['numero']) && $celda !== '' && $celda !== null)
                            {{ number_format((float) $celda, 2, ',', '.') }}
                        @else
                            {{ $celda }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
