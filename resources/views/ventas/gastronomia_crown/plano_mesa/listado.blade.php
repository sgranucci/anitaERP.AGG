@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresa->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $f = $filtros ?? [];
    $subtitulo = trim((string) ($f['valor'] ?? '')) !== ''
        ? 'Filtro: '.$f['valor']
        : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planos de mesas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 35%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 40%; text-align: center;">
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de planos de mesas</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtitulo !== '')
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 10%;">Código</th>
                <th style="width: 26%;">Nombre</th>
                <th style="width: 20%;">Empresa</th>
                <th style="width: 19%;">Sala</th>
                <th style="width: 7%;">Mesas</th>
                <th style="width: 6%;">Orden</th>
                <th style="width: 7%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->codigo }}</td>
                    <td>{{ $data->nombre }}</td>
                    <td>{{ optional($data->empresa)->nombre }}</td>
                    <td>{{ trim((optional($data->sala)->codigo ?? '').' '.(optional($data->sala)->nombre ?? '')) }}</td>
                    <td>{{ $data->cantidadMesas() }}</td>
                    <td>{{ (int) $data->orden }}</td>
                    <td>{{ $data->activo ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
