@php
    $filas = $filas ?? [];
    $resultado = $resultado ?? [];
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $colspan = 12;
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }
        return number_format($n, 2, ',', '.');
    };
    $subtitulo = ($resultado['empresa_nombre'] ?? '').' — '
        .(\Carbon\Carbon::parse($resultado['fecha_desde'] ?? now())->format('d/m/Y'))
        .' al '
        .(\Carbon\Carbon::parse($resultado['fecha_hasta'] ?? now())->format('d/m/Y'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conciliacion flash maquinas</title>
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
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Conciliaci&oacute;n flash m&aacute;quinas</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }} — Generado {{ date('d/m/Y H:i') }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Jornada</th>
            <th>Estado</th>
            <th>Rend.</th>
            <th class="num">Flash total</th>
            <th class="num">Flash slot</th>
            <th class="num">Flash ruleta</th>
            <th class="num">Rend. online</th>
            <th class="num">Rend. real</th>
            <th class="num">Flash-Rend.</th>
            <th class="num">Real-Online</th>
            <th>Pend.</th>
            <th>Cierre</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            <tr>
                <td>{{ $f['fecha_fmt'] ?? '' }}</td>
                <td>{{ $f['estado'] ?? '' }}</td>
                <td class="text-center">{{ (int) ($f['cantidad_rendiciones'] ?? 0) }}</td>
                <td class="num">
                    {{ $fmtNum($f['total_flash'] ?? 0) }}
                    @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($f['flash_validado']), 'soloTexto' => true])
                </td>
                <td class="num">
                    {{ $fmtNum($f['flash_slot'] ?? 0) }}
                    @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($f['flash_validado']), 'soloTexto' => true])
                </td>
                <td class="num">
                    {{ $fmtNum($f['flash_ruleta'] ?? 0) }}
                    @include('caja.flash.partials.tilde_validado', ['validado' => ! empty($f['flash_validado']), 'soloTexto' => true])
                </td>
                <td class="num">{{ $fmtNum($f['rendicion_online'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['rendicion_real'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['diferencia_flash_rendicion'] ?? 0) }}</td>
                <td class="num">{{ $fmtNum($f['diferencia_real_online'] ?? 0) }}</td>
                <td>{{ (int) ($f['cantidad_pendiente'] ?? 0) }}</td>
                <td>{{ $f['estado_cierre'] ?? '' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $colspan }}" style="text-align:center;">Sin datos</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
