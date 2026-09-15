@php
    $filas = $filas ?? [];
    $resultado = $resultado ?? [];
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $colspan = 11;
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($n == 0.0) {
            return '';
        }
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
    $subtitulo = ($resultado['empresa_nombre'] ?? '').' — '.($resultado['periodo_label'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asientos cierre maquinas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 3px 4px; }
        table.data thead tr { background-color: #85C1E9; color: #17202A; }
        .num { text-align: right; }
    </style>
</head>
<body>
<table class="data">
    @if ($esExcel && $reservarFilaLogoExcel)
        <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Asientos cierre m&aacute;quinas</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }} — Generado {{ date('d/m/Y H:i') }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Fecha asiento</th>
            <th>Jornada</th>
            <th>N&uacute;mero</th>
            <th>Tipo</th>
            <th>Origen</th>
            <th>Observaci&oacute;n</th>
            <th>Cuenta</th>
            <th>Descripci&oacute;n</th>
            <th>C. costo</th>
            <th class="num">Debe</th>
            <th class="num">Haber</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f['fecha_fmt'] ?? '' }}</td>
                <td>{{ $f['jornada_fmt'] ?? '' }}</td>
                <td>{{ $f['numero'] ?? '' }}</td>
                <td>{{ $f['tipo'] ?? '' }}</td>
                <td>{{ $f['origen'] ?? '' }}</td>
                <td>{{ $f['observacion'] ?? '' }}</td>
                <td>{{ $f['cuenta_codigo'] ?? '' }}</td>
                <td>{{ $f['cuenta_nombre'] ?? '' }}</td>
                <td>{{ $f['centrocosto'] ?? '' }}</td>
                <td class="num">{{ $fmtNum($f['debe'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['haber'] ?? 0) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $colspan }}" style="text-align:center;">Sin asientos en el per&iacute;odo.</td>
            </tr>
        @endforelse
        @if (($resultado['filas'] ?? []) !== [])
            <tr>
                <td colspan="9" style="text-align:right;"><strong>Totales ({{ (int) ($resultado['cantidad_asientos'] ?? 0) }} asiento(s))</strong></td>
                <td class="num"><strong>{{ $fmtNum($resultado['total_debe'] ?? 0) }}</strong></td>
                <td class="num"><strong>{{ $fmtNum($resultado['total_haber'] ?? 0) }}</strong></td>
            </tr>
        @endif
    </tbody>
</table>
</body>
</html>
