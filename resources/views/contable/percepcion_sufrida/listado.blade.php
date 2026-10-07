@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;

    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasParaLogo ?? []));
    $diferencias = $diferencias ?? [];
    $tot = $totales ?? [];
    $totalFilas = count($diferencias);
    $tituloReporte = $titulo ?? 'Diferencias';
    $esIibb = ! empty($esIibb);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7.5px; color: #1a1a1a; margin: 0; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr.columnas th {
            background-color: #85C1E9;
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .num { text-align: right; }
        .totales { margin-bottom: 8px; }
        .totales td { border: none; padding: 1px 4px; }
    </style>
</head>
<body>
    <table class="data">
        <thead>
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => $tituloReporte,
                'subtitulo' => $subtitulo ?? '',
                'colspan' => 8,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
            <tr class="columnas">
                <th style="width:9%;">Fecha</th>
                <th style="width:14%;">Comprobante</th>
                <th style="width:18%;">Emisor</th>
                <th style="width:12%;">CUIT</th>
                <th style="width:20%;">Descripción</th>
                <th style="width:9%;">Mayor</th>
                <th style="width:9%;">Reporte</th>
                <th style="width:9%;">Diferencia</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="8" style="border:none;padding:4px 2px;">
                    Mayor {{ number_format((float) ($tot['mayor'] ?? 0), 2, ',', '.') }}
                    @if ($esIibb)
                        — 901 {{ number_format((float) ($tot['cruzado_901'] ?? 0), 2, ',', '.') }}
                        — 902 {{ number_format((float) ($tot['cruzado_902'] ?? 0), 2, ',', '.') }}
                    @else
                        — Cruzado {{ number_format((float) ($tot['cruzado'] ?? 0), 2, ',', '.') }}
                    @endif
                    — Diferencias {{ number_format((float) ($tot['diferencias'] ?? 0), 2, ',', '.') }}
                </td>
            </tr>
            @forelse ($diferencias as $fila)
                <tr>
                    <td>{{ ($fila['fecha'] ?? '') !== '' ? date('d/m/Y', strtotime($fila['fecha'])) : '' }}</td>
                    <td>{{ trim(($fila['tipo'] ?? '').' '.($fila['comprobante'] ?? '')) }}</td>
                    <td>{{ ($fila['emisor_nombre'] ?? '') !== '' ? $fila['emisor_nombre'] : ($fila['emisor'] ?? '') }}</td>
                    <td>{{ PercepcionSufridaArchivoSupport::cuitConGuiones((string) ($fila['cuit'] ?? '')) }}</td>
                    <td>{{ $fila['descripcion'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($fila['importe_mayor'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['importe_reporte'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) ($fila['diferencia'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Sin diferencias.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
