@php
    $resultado = $resultado ?? [];
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $filas = $filas ?? [];
    $colspan = 17;
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $subtitulo = ($resultado['empresa_nombre'] ?? '').' — '
        .(\Carbon\Carbon::parse($resultado['fecha_desde'] ?? now())->format('d/m/Y'))
        .' al '
        .(\Carbon\Carbon::parse($resultado['fecha_hasta'] ?? now())->format('d/m/Y'))
        .' — '.(int) ($resultado['cantidad_dias'] ?? 0).' jornada(s)';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Venta de maquinas</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 2px 3px; }
        table.data thead tr { background-color: #85C1E9; color: #17202A; }
        .num { text-align: right; }
        tr.total { background: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body>
@php
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v, int $dec = 2) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if (abs($n) < 0.0000001) {
            return '';
        }
        if ($esExcel && $autoExcelNum) {
            return number_format($n, $dec, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, $dec);
        }

        return number_format($n, $dec, ',', '.');
    };
    $fmtNumCero = function ($v, int $dec = 2) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, $dec, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, $dec);
        }

        return number_format($n, $dec, ',', '.');
    };
@endphp
<table class="data">
    @if ($esExcel && $reservarFilaLogoExcel)
        <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Venta de m&aacute;quinas</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }} — Generado {{ date('d/m/Y H:i') }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Fecha</th>
            <th class="num">Maquinas</th>
            <th class="num">Total On Line</th>
            <th class="num">Diferencia</th>
            <th class="num">Ef.+euros en $</th>
            <th class="num">Efectivo</th>
            <th class="num">Tarj. Visa</th>
            <th class="num">Tarj. Master</th>
            <th class="num">MEP</th>
            <th class="num">Total coin</th>
            <th class="num">Euros</th>
            <th class="num">Cot.Euro</th>
            <th class="num">Euros en $</th>
            <th class="num">Dolares</th>
            <th class="num">Cot.Dolar</th>
            <th class="num">Dolares en $</th>
            <th class="num">Caja trans. $</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            @php $esTot = ! empty($f['es_total']); @endphp
            <tr @if ($esTot) class="total" @endif>
                <td>{{ $f['fecha_fmt'] ?? '' }}</td>
                <td class="num">{{ $fmtNumCero($f['maquinas'] ?? 0) }}</td>
                <td class="num">{{ $fmtNumCero($f['total_online'] ?? 0) }}</td>
                <td class="num">{{ $fmtNumCero($f['diferencia'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['efectivo_euro'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['efectivo'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['visa'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['master'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['mep'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['totalcoin'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['euros'] ?? 0) }}</td>
                <td class="num">{{ $esTot ? '' : $fmtNum($f['cot_euro'] ?? 0, 4) }}</td>
                <td class="num">{{ $fmtNum($f['euros_en_pesos'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['dolares'] ?? 0) }}</td>
                <td class="num">{{ $esTot ? '' : $fmtNum($f['cot_dolar'] ?? 0, 4) }}</td>
                <td class="num">{{ $fmtNum($f['dolares_en_pesos'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['caja_trans'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
