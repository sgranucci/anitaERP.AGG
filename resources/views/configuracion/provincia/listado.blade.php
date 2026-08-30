@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Configuracion\ProvinciaTasaiibbListadoSupport;
    $filas = $filas ?? collect();
    foreach ($filas as $row) {
        if (! isset($row->nombreempresa) || $row->nombreempresa === '') {
            $row->nombreempresa = (string) config('app.empresa');
        }
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $tituloReporte = $titulo ?? ProvinciaTasaiibbListadoSupport::titulo();
    $subtituloReporte = $subtitulo ?? ProvinciaTasaiibbListadoSupport::subtitulo();
    $resumen = $resumen ?? ProvinciaTasaiibbListadoSupport::resumen($filas);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
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
        table.data td.num, table.data th.num { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 style="margin: 0; font-size: 16px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if ($subtituloReporte !== '')
                    <div class="meta">{{ $subtituloReporte }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                Provincias: {{ (int) ($resumen['provincias'] ?? 0) }}<br>
                Al&iacute;cuotas: {{ (int) ($resumen['alicuotas'] ?? 0) }}
            </td>
        </tr>
    </table>
    @include('configuracion.provincia.partials.tabla_tasas', ['filas' => $filas])
</body>
</html>
