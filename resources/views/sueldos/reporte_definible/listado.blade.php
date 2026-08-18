<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 4px 0; }
        .meta { font-size: 8px; margin-bottom: 8px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; }
        .logos img { height: 36px; margin-right: 8px; }
    </style>
</head>
<body>
@if (!empty($logos))
    <div class="logos">
        @foreach ($logos as $logo)
            @if (!empty($logo))
                <img src="{{ $logo }}">
            @endif
        @endforeach
    </div>
@endif
<h1>{{ $titulo }}</h1>
<div class="meta">
    Generado {{ date('d/m/Y H:i') }}
    @if (!empty($subtitulo))
        — {{ $subtitulo }}
    @endif
    — {{ count($resultado['filas'] ?? []) }} registros
</div>
<table class="data">
    <thead>
        <tr>
            <th>Legajo</th>
            <th>Nombre</th>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <th>{{ $col['descripcion'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($resultado['filas'] ?? [] as $fila)
            <tr>
                <td>{{ $fila['legajo'] ?? '' }}</td>
                <td>{{ $fila['nombre'] ?? '' }}</td>
                @foreach ($resultado['columnas'] ?? [] as $col)
                    @php $val = $fila['c'.$col['nro']] ?? ''; @endphp
                    <td class="{{ !empty($col['numerica']) ? 'num' : '' }}">
                        {{ !empty($col['numerica']) && is_numeric($val) ? number_format((float) $val, 2, ',', '.') : $val }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
