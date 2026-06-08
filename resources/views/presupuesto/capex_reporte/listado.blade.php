@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $tituloReporte = $titulo ?? 'Reporte CAPEX';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
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
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 6.5px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
        .num { text-align: right; white-space: nowrap; }
        .cell-partidas { font-size: 6.5px; white-space: normal; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Seguimiento OC · facturas · pagos — {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                @if (!empty($totalFilas))
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        <colgroup>
            <col style="width: 3%;">
            <col style="width: 4%;">
            <col style="width: 7%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 3%;">
            <col style="width: 3%;">
            <col style="width: 3%;">
            <col style="width: 3%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 3%;">
            <col style="width: 7%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 4%;">
            <col style="width: 5%;">
            <col style="width: 5%;">
            <col style="width: 10%;">
        </colgroup>
        @include('presupuesto.capex_reporte.partials.tabla_datos', ['filas' => $filas])
    </table>
</body>
</html>
