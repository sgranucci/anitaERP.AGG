@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Exports\Contable\CierreTurnoGastronomiaContableConciliacionExport;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $filas = $filas ?? CierreTurnoGastronomiaContableConciliacionExport::aplanarFilas($resultado ?? []);
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
    $colspan = 11;
    $totFact = 0.0;
    $totFlash = 0.0;
    $totAsientos = 0.0;
    $totMayor = 0.0;
    $totCierres = 0;
    $totDifFlash = 0.0;
    $totDifAsientos = 0.0;
    $totDifMayor = 0.0;
    foreach ($filas as $fSum) {
        $totFact += (float) ($fSum['total_facturacion'] ?? 0);
        $totFlash += (float) ($fSum['total_flash_ayb'] ?? 0);
        $totAsientos += (float) ($fSum['total_asientos_debe'] ?? 0);
        $totMayor += (float) ($fSum['total_mayor_neto'] ?? 0);
        $totCierres += (int) ($fSum['cantidad_cierres'] ?? 0);
        $totDifFlash += (float) ($fSum['diferencia_flash'] ?? 0);
        $totDifAsientos += (float) ($fSum['diferencia_asientos'] ?? 0);
        $totDifMayor += (float) ($fSum['diferencia_mayor'] ?? 0);
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Conciliacion gastro</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 2px 3px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; font-size: 6px; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 7px; color: #444; margin-top: 2px; }
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
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Conciliaci&oacute;n gastronom&iacute;a Contable</strong></td>
            </tr>
            <tr>
                <td colspan="{{ $colspan }}"><strong>Generado {{ date('d/m/Y H:i') }} — {{ $subtitulo }}</strong></td>
            </tr>
        </tbody>
    </table>
@else
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 150px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 13px; font-weight: bold;">Conciliaci&oacute;n gastronom&iacute;a Contable</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 7px;">
                Filas: {{ count($filas) }}
            </td>
        </tr>
    </table>
@endif

<table class="data">
    <thead>
        <tr>
            <th>Jornada</th>
            <th>Flash fecha</th>
            <th>Estado</th>
            <th class="num">Cierres</th>
            <th class="num">Facturaci&oacute;n</th>
            <th class="num">Flash ayb</th>
            <th class="num">Asientos</th>
            <th class="num">Mayor</th>
            <th class="num">Dif. flash</th>
            <th class="num">Dif. asientos</th>
            <th class="num">Dif. mayor</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['fecha_jornada'] ?? '' }}</td>
                <td>{{ $fila['fecha_flash'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td class="num">{{ (int) ($fila['cantidad_cierres'] ?? 0) }}</td>
                <td class="num">{{ number_format((float) ($fila['total_facturacion'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['total_flash_ayb'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['total_asientos_debe'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['total_mayor_neto'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['diferencia_flash'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['diferencia_asientos'] ?? 0), 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) ($fila['diferencia_mayor'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}" style="text-align:center;">Sin actividad</td>
            </tr>
        @endforelse
    </tbody>
    @if (count($filas) > 0)
        <tfoot>
            <tr style="background-color:#d6eaf8;font-weight:bold;">
                <td colspan="2">Totales ({{ count($filas) }} d&iacute;as)</td>
                <td></td>
                <td class="num">{{ $totCierres }}</td>
                <td class="num">{{ number_format($totFact, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totFlash, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totAsientos, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totMayor, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totDifFlash, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totDifAsientos, 2, ',', '.') }}</td>
                <td class="num">{{ number_format($totDifMayor, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    @endif
</table>
</body>
</html>
