@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Exports\Contable\GastronomiaDiarioPuntoventaExport;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $resultado = $resultado ?? [];
    $matriz = $matriz ?? GastronomiaDiarioPuntoventaExport::matrizAncha($resultado);
    $bloquesPv = $matriz['bloques_pv'] ?? [];
    $filas = $matriz['filas'] ?? [];
    $labelsFila2 = $matriz['labels_fila2'] ?? [];
    $colspan = max(1, (int) ($matriz['cantidad_columnas'] ?? 1));
    $empresaNombre = (string) ($resultado['empresa_nombre'] ?? '');
    $paraLogos = collect([(object) ['nombreempresa' => $empresaNombre]]);
    $logosCabecera = $esExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($paraLogos);
    $subtitulo = trim(
        $empresaNombre
        .' — '
        .\Carbon\Carbon::parse($resultado['fecha_desde'] ?? now())->format('d/m/Y')
        .' al '
        .\Carbon\Carbon::parse($resultado['fecha_hasta'] ?? now())->format('d/m/Y')
    );
    $resumen = $resultado['resumen'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Diario PV gastro</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6.5px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 2px 3px; vertical-align: middle; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; font-size: 6px; text-align: center; }
        .num { text-align: right; }
        .centro { text-align: center; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
        .th-pv { background-color: #85C1E9; font-size: 6.5px; }
        .th-medio { background-color: #85C1E9; }
        .th-venta, .th-iva, .th-nc { background-color: #5DADE2; }
        .th-total-dia { background-color: #5DADE2 !important; }
        .fecha { font-weight: bold; text-align: center; white-space: nowrap; }
    </style>
</head>
<body>
@if ($esExcel)
    <table>
        @if ($reservarFilaLogoExcel)
            <tbody>
                <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
            </tbody>
        @endif
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}"><strong style="font-size: 14pt;">Diario gastronom&iacute;a por PV / medios (Contable)</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}">
                    Neto {{ number_format((float) ($resumen['venta_neta'] ?? 0), 2, ',', '.') }}
                    — {{ (int) ($resumen['cantidad_dias'] ?? 0) }} jornada(s)
                    — {{ count($bloquesPv) }} PV
                    — columnas: FECHA + por cada PV: medios, Neto, IVA y NC + TOTAL DÍA
                </td>
            </tr>
        </tbody>
    </table>
@else
    <table class="listado-header">
        <tr>
            <td style="width: 22%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 130px; margin-right: 6px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 56%; text-align: center;">
                <h2 style="margin: 0; font-size: 12px; font-weight: bold;">Diario gastronom&iacute;a por PV / medios (Contable)</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
                <div class="meta">
                    Neto {{ number_format((float) ($resumen['venta_neta'] ?? 0), 2, ',', '.') }}
                    — {{ (int) ($resumen['cantidad_dias'] ?? 0) }} jornada(s)
                    — {{ count($bloquesPv) }} PV
                </div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 6.5px;">
                FECHA + PV a lo ancho<br>
                (medios / Neto / IVA / NC)
            </td>
        </tr>
    </table>
@endif

@if ($bloquesPv === [])
    <p style="text-align:center;">Sin facturaci&oacute;n gastronom&iacute;a en el rango indicado.</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th rowspan="2" class="th-pv" style="vertical-align: middle;">FECHA</th>
                @foreach ($bloquesPv as $bloque)
                    <th colspan="{{ (int) ($bloque['cantidad_columnas'] ?? 2) }}"
                        class="th-pv {{ ! empty($bloque['es_total_dia']) ? 'th-total-dia' : '' }}">
                        {{ $bloque['titulo'] ?? '' }}
                    </th>
                @endforeach
            </tr>
            <tr>
                @foreach ($bloquesPv as $bloque)
                    @php $esTotal = ! empty($bloque['es_total_dia']); @endphp
                    @foreach ($bloque['labels_medios'] ?? [] as $labelMedio)
                        <th class="th-medio {{ $esTotal ? 'th-total-dia' : '' }}">{{ $labelMedio }}</th>
                    @endforeach
                    <th class="th-venta {{ $esTotal ? 'th-total-dia' : '' }}">Neto</th>
                    <th class="th-iva {{ $esTotal ? 'th-total-dia' : '' }}">IVA</th>
                    <th class="th-nc {{ $esTotal ? 'th-total-dia' : '' }}">NC</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
                <tr>
                    <td class="fecha">{{ $fila['fecha'] ?? '' }}</td>
                    @foreach ($fila['valores'] ?? [] as $valor)
                        <td class="num">
                            @if ($valor !== null)
                                {{ number_format((float) $valor, 2, ',', '.') }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $colspan }}" class="centro">Sin actividad</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endif
</body>
</html>
