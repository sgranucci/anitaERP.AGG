@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Exports\Contable\CierreRendicionEstacionamientoConciliacionFlashExport;

    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $filas = $filas ?? CierreRendicionEstacionamientoConciliacionFlashExport::aplanarFilas($resultado ?? []);
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
    $colspan = 10;
    $formatoExcelNum = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoExcelNum);
    $fmtNum = function ($v) use ($esExcel, $formatoExcelNum, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoExcelNum, 2);
        }
        return number_format($n, 2, ',', '.');
    };
    $totRend = 0;
    $totFact = 0.0;
    $totNc = 0.0;
    $totVenta = 0.0;
    $totFlash = 0.0;
    $totAsientos = 0.0;
    $totDifFlash = 0.0;
    $totDifAsientos = 0.0;
    foreach ($filas as $fSum) {
        $totRend += (int) ($fSum['cantidad_rendiciones'] ?? 0);
        $totFact += (float) ($fSum['total_facturacion'] ?? 0);
        $totNc += (float) ($fSum['total_notas_credito'] ?? 0);
        $totVenta += (float) ($fSum['total_ventas_brutas'] ?? 0);
        $totFlash += (float) ($fSum['total_flash_estac'] ?? 0);
        $totAsientos += (float) ($fSum['total_asientos_debe'] ?? 0);
        $totDifFlash += (float) ($fSum['diferencia_flash'] ?? 0);
        $totDifAsientos += (float) ($fSum['diferencia_venta_asientos'] ?? 0);
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Conciliacion flash estac.</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            padding: 2px 3px;
            vertical-align: top;
        }
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
                <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Conciliaci&oacute;n flash estacionamiento</strong></td>
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
                <h2 style="margin: 0; font-size: 13px; font-weight: bold;">Conciliaci&oacute;n flash estacionamiento</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 7px;">
                Filas: {{ count($filas) }}
            </td>
        </tr>
    </table>
    <p style="font-size: 6.5px; color: #555; margin: 4px 0 8px;">
        Fact. neta = ventas netas (comparable a flash).
        Venta total = neta + NC (comparable a &Sigma; debe del asiento).
        Dif. flash = fact. neta − flash_estac.
        Dif. asientos = venta total − &Sigma; debe asientos.
    </p>
@endif

<table class="data">
    <thead>
        <tr>
            <th>Jornada</th>
            <th>Estado</th>
            <th class="num">Rend.</th>
            <th class="num">Fact. neta</th>
            <th class="num">NC</th>
            <th class="num">Venta total</th>
            <th class="num">Flash estac.</th>
            <th class="num">Asientos</th>
            <th class="num">Dif. fact.&minus;flash</th>
            <th class="num">Dif. venta&minus;asientos</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['fecha_jornada_fmt'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td class="num">{{ (int) ($fila['cantidad_rendiciones'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($fila['total_facturacion'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($fila['total_notas_credito'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($fila['total_ventas_brutas'] ?? 0) }}</td>
                <td class="num">
                    {{ $fmtNum($fila['total_flash_estac'] ?? 0) }}
                    @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($fila['flash_validado']), 'soloTexto' => true])
                </td>
                <td class="num">{{ $fmtNum($fila['total_asientos_debe'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($fila['diferencia_flash'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($fila['diferencia_venta_asientos'] ?? 0) }}</td>
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
                <td>Totales ({{ count($filas) }} d&iacute;as)</td>
                <td></td>
                <td class="num">{{ $totRend }}</td>
                <td class="num">{{ $fmtNum($totFact) }}</td>
                <td class="num">{{ $fmtNum($totNc) }}</td>
                <td class="num">{{ $fmtNum($totVenta) }}</td>
                <td class="num">{{ $fmtNum($totFlash) }}</td>
                <td class="num">{{ $fmtNum($totAsientos) }}</td>
                <td class="num">{{ $fmtNum($totDifFlash) }}</td>
                <td class="num">{{ $fmtNum($totDifAsientos) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
</body>
</html>
