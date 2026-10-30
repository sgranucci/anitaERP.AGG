@php
    use App\Support\Stock\FormulaArticuloListadoColumnas;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 18px 32px 18px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        table.data thead tr.columnas > th { background: #85C1E9; color: #17202A; font-weight: bold; }
        table.data tbody tr:nth-child(even) td { background: #f5f5f5; }
        td.num, th.num { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
<table class="data">
    <thead>
        @include('includes.reportes.pdf_thead_cabecera', [
            'titulo' => 'Fórmulas de artículos',
            'subtitulo' => $subtitulo ?? '',
            'colspan' => count($columnasVisibles ?? []),
            'logosCabecera' => $logosCabecera ?? [],
            'totalFilas' => $totalFilas ?? 0,
        ])
        <tr class="columnas">
            @foreach ($columnasVisibles as $keyColumna)
                <th @if ($keyColumna === 'cantidadunidad') class="num" @endif>{{ ($etiquetasColumnas ?? [])[$keyColumna] ?? $keyColumna }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($formulas as $data)
            <tr>
                @foreach ($columnasVisibles as $keyColumna)
                    @if ($keyColumna === 'items')
                        <td class="cell-items">@include('stock.formula_articulo.partials.export_lineas', ['data' => $data, 'separator' => '<br>', 'enlaces' => false])</td>
                    @else
                        <td @if ($keyColumna === 'cantidadunidad') class="num" @endif>{{ FormulaArticuloListadoColumnas::valorCelda($data, $keyColumna) }}</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
