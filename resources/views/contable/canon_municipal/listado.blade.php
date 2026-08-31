@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filasParaLogo = $filasParaLogo ?? [];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasParaLogo));
    $tituloReporte = $titulo ?? 'Canon municipal bingo';
    $resultado = $resultado ?? [];
    $filas = $resultado['filas'] ?? [];
    $resumen = $resultado['resumen'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8px;
            color: #1a1a1a;
            margin: 0;
        }
        table.data {
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            padding: 2px 4px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 6px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width:32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height:52px; max-width:160px; margin-right:8px;">
                @endforeach
            </td>
            <td style="width:46%; text-align:center;">
                <h2 style="margin:0; font-size:14px;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width:22%; text-align:right;" class="meta">
                @if (! empty($resultado['cuadra']))
                    Estado: Cuadra
                @else
                    Estado: No cuadra
                @endif
            </td>
        </tr>
    </table>

    <p class="meta">
        Flash {{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}
        · Posición {{ number_format((float) ($resumen['total_posicion'] ?? 0), 2, ',', '.') }}
        · Dif. {{ number_format((float) ($resumen['diferencia'] ?? 0), 2, ',', '.') }}
        · Canon {{ number_format((float) ($resumen['canon_4'] ?? 0), 2, ',', '.') }}
        · Días {{ (int) ($resumen['dias_con_venta'] ?? 0) }}/{{ (int) ($resumen['dias_rango'] ?? 0) }}
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="num">Flash</th>
                <th class="num">Posición</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
                <th class="num">Canon</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    <td>{{ date('d/m/Y', strtotime($fila['fecha'])) }}</td>
                    <td class="num">{{ number_format((float) $fila['venta_flash'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila['venta_posicion'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila['diferencia'], 2, ',', '.') }}</td>
                    <td>{{ ! empty($fila['cuadra']) ? 'OK' : 'Desvío' }}</td>
                    <td class="num">{{ number_format((float) $fila['canon'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>TOTALES</strong></td>
                <td class="num"><strong>{{ number_format((float) ($resumen['total_flash'] ?? 0), 2, ',', '.') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($resumen['total_posicion'] ?? 0), 2, ',', '.') }}</strong></td>
                <td class="num"><strong>{{ number_format((float) ($resumen['diferencia'] ?? 0), 2, ',', '.') }}</strong></td>
                <td></td>
                <td class="num"><strong>{{ number_format((float) ($resumen['canon_4'] ?? 0), 2, ',', '.') }}</strong></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
