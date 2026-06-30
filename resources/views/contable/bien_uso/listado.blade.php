@php
    use App\Models\Contable\BienUso;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $nombreEmpresa = (string) config('app.empresa', '');
    foreach ($datas as $row) {
        $row->nombreempresa = $nombreEmpresa;
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Bienes de uso</title>
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de bienes de uso</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
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
                <th style="width: 4%;">ID</th>
                <th style="width: 7%;">UID</th>
                <th style="width: 5%;">C&oacute;d. inv.</th>
                <th style="width: 10%;">Empresa</th>
                <th style="width: 10%;">Hostname</th>
                <th style="width: 7%;">IP</th>
                <th style="width: 10%;">Modelo</th>
                <th style="width: 9%;">Vendor</th>
                <th style="width: 10%;">Tema</th>
                <th style="width: 8%;">N&ordm; serie</th>
                <th style="width: 6%;">Estado</th>
                <th style="width: 8%;">C. costo</th>
                <th style="width: 6%;">Tipo bien</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->uid }}</td>
                <td>{{ $data->codigo_inventario }}</td>
                <td>{{ $data->empresa->nombre ?? '' }}</td>
                <td>{{ $data->hostname }}</td>
                <td>{{ $data->ip }}</td>
                <td>{{ $data->modelo }}</td>
                <td>{{ $data->vendor }}</td>
                <td>{{ $data->tema }}</td>
                <td>{{ $data->numero_serie }}</td>
                <td>{{ BienUso::labelEstado($data->estado) }}</td>
                <td>{{ $data->centrocostos->codigo ?? '' }} — {{ $data->centrocostos->nombre ?? '' }}</td>
                <td>{{ BienUso::labelTipoBien($data->tipo_bien) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
