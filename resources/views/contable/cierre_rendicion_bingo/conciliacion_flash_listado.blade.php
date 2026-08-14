@php
    $filas = $filas ?? [];
    $resultado = $resultado ?? [];
    $esExcel = ! empty($esExcel);
    $reservarFilaLogoExcel = ! empty($reservarFilaLogoExcel);
    $gruposColumnas = $resultado['grupos_columnas'] ?? [];
    $columnas = $resultado['columnas'] ?? [];
    $colspan = max(1, count($columnas));
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
    <title>Conciliacion bingo vs flash</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6.5px; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #ccc; padding: 2px 3px; }
        table.data thead tr { background-color: #85C1E9; color: #17202A; }
        .num { text-align: right; }
        .flash { background-color: #D6EAF8; }
    </style>
</head>
<body>
<table class="data">
    @if ($esExcel && $reservarFilaLogoExcel)
        <tr><td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">Conciliación venta de sala de bingo vs flash</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}"><strong>{{ $subtitulo }} — Generado {{ date('d/m/Y H:i') }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">{{ (int) (($resultado['resumen']['total_dias'] ?? 0)) }} jornada(s) — {{ (int) ($resultado['resumen']['dias_ok'] ?? 0) }} OK, {{ (int) ($resultado['resumen']['dias_dif'] ?? 0) }} con diferencia</td>
    </tr>
    <thead>
        <tr>
            @foreach ($gruposColumnas as $grupo)
                @php $span = max(1, count($grupo['columnas'] ?? [])); @endphp
                @if ($span > 1)
                    <th colspan="{{ $span }}">{{ $grupo['titulo'] ?? '' }}</th>
                @else
                    <th>{{ $grupo['titulo'] ?? '' }}</th>
                @endif
            @endforeach
        </tr>
        <tr>
            @foreach ($gruposColumnas as $grupo)
                @foreach ($grupo['columnas'] ?? [] as $col)
                    <th>{{ $col['subtitulo'] ?? '' }}</th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $f)
            @php $estiloTotal = ! empty($f['es_total']) ? 'background:#D6EAF8;font-weight:bold;' : ''; @endphp
            <tr style="{{ $estiloTotal }}">
                @foreach ($columnas as $col)
                    @php
                        $key = (string) ($col['key'] ?? '');
                        $tipo = (string) ($col['tipo'] ?? 'texto');
                        $valor = $f[$key] ?? '';
                        $esFlash = ($col['grupo'] ?? '') === 'flash';
                    @endphp
                    @if ($tipo === 'numero')
                        <td class="num{{ $esFlash ? ' flash' : '' }}">{{ $fmtNum($valor) }}</td>
                    @else
                        <td class="{{ $esFlash ? 'flash' : '' }}">{{ $valor }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ $colspan }}" style="text-align:center;">Sin datos</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
