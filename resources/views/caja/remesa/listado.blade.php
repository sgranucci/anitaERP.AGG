@php
    use App\Support\Caja\Remesa\RemesaSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $tipoLabels = [
        RemesaSupport::TIPO_INTERNA => 'Interna',
        RemesaSupport::TIPO_EXTERNA => 'Externa',
    ];
    $estadoLabels = [
        RemesaSupport::ESTADO_CONFIRMADA => 'Confirmada',
        RemesaSupport::ESTADO_ANULADA => 'Anulada',
    ];
    foreach ($datas as $row) {
        $row->nombreempresa = (string) ($row->empresa->nombre ?? '');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = $datas->count();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Remesas</title>
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
        table.data th { font-size: 7px; font-weight: bold; color: #17202A; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
        .text-right { text-align: right; }
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
                <h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de remesas</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $totalFilas }} registro(s)</div>
            </td>
            <td style="width: 25%;"></td>
        </tr>
    </table>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 6%;">N&deg;</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 8%;">Tipo</th>
                <th style="width: 18%;">Empresa</th>
                <th style="width: 10%;">Destino</th>
                <th style="width: 10%;">Origen</th>
                <th style="width: 10%;">Remito</th>
                <th style="width: 10%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->numero }}</td>
                <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                <td>{{ $tipoLabels[$row->tipo] ?? $row->tipo }}</td>
                <td>{{ $row->empresa->nombre ?? '' }}</td>
                <td class="text-right">{{ number_format((float) $row->importe_destino, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $row->importe_origen, 2, ',', '.') }}</td>
                <td>{{ $row->remito }}</td>
                <td>{{ $estadoLabels[$row->estado] ?? $row->estado }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
