@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filasParaLogo = $filasParaLogo ?? [];
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasParaLogo));
    $tituloReporte = $titulo ?? 'SUSS';
    $registros = $registros ?? [];
    $totales = $totales ?? [];
    $conciliacion = $conciliacion ?? [];
    $totalFilas = (int) ($totales['registros'] ?? count($registros));
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
            font-size: 7.5px;
            color: #1a1a1a;
            margin: 0;
        }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        table.tabla-conciliacion { font-size: 7px; }
        table.tabla-detalle { font-size: 7.5px; }
        .listado-header { width: 100%; margin-bottom: 6px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
        .num { text-align: right; }
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
                <h2 style="margin: 0; font-size: 15px; font-weight: bold;">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (! empty($subtitulo))
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                Registros: {{ $totalFilas }}
            </td>
        </tr>
    </table>

    @include('contable.suss.partials.tabla_listado', [
        'registros' => $registros,
        'totales' => $totales,
        'conciliacion' => $conciliacion,
        'esExcel' => false,
    ])
</body>
</html>
