@php
    use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $nombre = trim((string) ($row->nombreempresa ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($row->empresas?->nombre ?? ''));
        }
        $row->nombreempresa = $nombre !== '' ? $nombre : (string) config('app.empresa');
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $monedasColumnas = $monedasColumnas ?? CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
    $cantidadMonedas = $monedasColumnas->count();
    $anchoFijo = 12;
    $anchoMoneda = $cantidadMonedas > 0 ? max(6, (100 - $anchoFijo) / ($cantidadMonedas * 2)) : 8;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización tesorería</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px;
            vertical-align: middle;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 6px;
            font-weight: bold;
            color: #17202A;
            text-align: center;
        }
        table.data td.num { text-align: right; }
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
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">Listado de cotización tesorería</h2>
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
                <th style="width: 4%;" rowspan="2">ID</th>
                <th style="width: 8%;" rowspan="2">Empresa</th>
                <th style="width: 6%;" rowspan="2">Fecha</th>
                @foreach ($monedasColumnas as $moneda)
                    <th colspan="2">{{ $moneda->label }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach ($monedasColumnas as $moneda)
                    <th style="width: {{ number_format($anchoMoneda, 1) }}%;">Compra</th>
                    <th style="width: {{ number_format($anchoMoneda, 1) }}%;">Venta</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($datas as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->nombreempresa ?? $data->empresas?->nombre ?? $data->empresa_id }}</td>
                    <td>{{ $data->fecha ? $data->fecha->format('d/m/Y') : '' }}</td>
                    @foreach ($monedasColumnas as $moneda)
                        <td class="num">{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaCompra((int) $moneda->codigo)) }}</td>
                        <td class="num">{{ CotizacionTesoreriaMonedasSupport::formatear($data->tasaVenta((int) $moneda->codigo)) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
