@php
    $tituloReporte = $titulo ?? 'Recetas';
    $subtituloReporte = $subtitulo ?? '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tituloReporte }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 9px; color: #444; margin-bottom: 8px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 5px 6px; vertical-align: middle; font-size: 10px; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>{{ $tituloReporte }}</h1>
    <div class="meta">{{ $subtituloReporte }}</div>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Receta</th>
                <th>Estado</th>
                <th>Categoría</th>
                <th>Subcategoría</th>
                <th class="text-right">Porc.</th>
                <th class="text-right">Costo porción</th>
                <th class="text-right">Costo lote</th>
                <th class="text-right">Precio</th>
                <th class="text-right">P/C</th>
                <th>Alerta</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado['recetas'] ?? [] as $fila)
                <tr>
                    <td>{{ $fila['codigo'] }}</td>
                    <td>{{ $fila['nombre'] }}</td>
                    <td>{{ $fila['estado_articulo'] }}</td>
                    <td>{{ $fila['categoria'] }}</td>
                    <td>{{ $fila['subcategoria'] }}</td>
                    <td class="text-right">{{ number_format((float) $fila['porciones'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['costo'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['costo_lote'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) $fila['precio'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ $fila['ratio'] === null ? '' : number_format((float) $fila['ratio'], 2, ',', '.') }}</td>
                    <td>{{ $fila['alerta'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
