@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $resultadosPdf = $resultados ?? null;
    if (! is_array($resultadosPdf) || $resultadosPdf === []) {
        $resultadosPdf = [[
            'filas_ordenadas' => $filas ?? [],
            'dias' => $dias ?? [],
            'mes' => null,
            'anio' => null,
            'fuente_etiqueta' => null,
        ]];
    }

    $filasLogo = $resultadosPdf[0]['filas_ordenadas'] ?? ($filas ?? []);
    $coleccionLogos = collect($filasLogo)->map(function ($fila) use ($empresa) {
        return (object) [
            'etiqueta' => $fila['etiqueta'] ?? '',
            'valor' => $fila['valor'] ?? 0,
            'nombreempresa' => (string) ($empresa->nombre ?? ''),
        ];
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Posición financiera</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 6px;
            font-weight: bold;
            color: #17202A;
            text-align: right;
        }
        table.data th.posfin-concepto { text-align: left; }
        table.data td.posfin-concepto { white-space: nowrap; }
        table.data td.posfin-dia,
        table.data td.posfin-total-col { text-align: right; }
        table.data tr.posfin-titulo td { background-color: #D6EAF8; font-weight: bold; }
        table.data tr.posfin-total { font-weight: bold; }
        table.data tr.posfin-informativo td {
            background-color: #FFF3CD;
            color: #664D03;
            font-weight: bold;
        }
        .posfin-informativo-aviso { font-size: 5px; text-transform: uppercase; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 7px; color: #444; margin-top: 3px; }
        table.data td.num, table.data th.num, td.num, th.num { text-align: right; }
        .posfin-salto { page-break-before: always; }
    </style>
</head>
<body>
@foreach ($resultadosPdf as $idxPdf => $resultadoPdf)
    @php
        $filasPdf = $resultadoPdf['filas_ordenadas'] ?? [];
        $diasPdf = $resultadoPdf['dias'] ?? [];
        $mesPdf = (int) ($resultadoPdf['mes'] ?? 0);
        $anioPdf = (int) ($resultadoPdf['anio'] ?? 0);
        $periodoPdf = $mesPdf > 0 && $anioPdf > 0
            ? str_pad((string) $mesPdf, 2, '0', STR_PAD_LEFT).'/'.$anioPdf
            : ($periodo_texto ?? '');
        $fuentePdf = (string) ($resultadoPdf['fuente_etiqueta'] ?? '');
    @endphp
    <div class="{{ $idxPdf > 0 ? 'posfin-salto' : '' }}">
        <table class="listado-header">
            <tr>
                <td style="width: 28%;">
                    @foreach ($logosCabecera as $logo)
                        <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 160px; margin-right: 8px; margin-bottom: 4px; vertical-align: middle;">
                    @endforeach
                </td>
                <td style="width: 72%; text-align: center;">
                    <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Posición financiera</h2>
                    <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                    @if ($periodoPdf !== '' || $empresa)
                        <div class="meta">
                            @if ($empresa)
                                {{ $empresa->nombre }}
                            @endif
                            @if ($periodoPdf !== '')
                                · Período {{ $periodoPdf }}
                            @endif
                            @if ($fuentePdf !== '')
                                · {{ $fuentePdf }}
                            @endif
                        </div>
                    @endif
                    <div class="meta">{{ count($filasPdf) }} conceptos · una columna por día</div>
                </td>
            </tr>
        </table>

        @include('caja.posicion_financiera.partials.tabla_datos', [
            'filas' => $filasPdf,
            'dias' => $diasPdf,
            'modo' => 'pdf',
        ])
    </div>
@endforeach
</body>
</html>
