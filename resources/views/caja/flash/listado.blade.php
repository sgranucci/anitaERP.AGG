@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Flash diario</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 4px; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 20px;">Flash diario</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
            </td>
            <td style="width: 25%; text-align: right;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>ID</th>
                <th>Empresa</th>
                <th>AyB</th>
                <th>Estac.</th>
                <th>Bingo</th>
                <th>Gaming</th>
                <th>Revenues</th>
                <th>Comentario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
            <tr>
                <td>{{ $data->fecha?->format('d/m/Y') }}</td>
                <td>{{ $data->id }}</td>
                <td>{{ $data->empresa->nombre ?? '' }}</td>
                <td class="text-right">{{ number_format((float) $data->ayb, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $data->estac, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $data->bingo_total_venta, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data->total_gaming, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($data->total_revenues, 2, ',', '.') }}</td>
                <td>{{ $data->comentario }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
