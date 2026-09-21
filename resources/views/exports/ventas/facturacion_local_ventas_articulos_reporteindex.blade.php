@php
    $filas = $resultado['filas'] ?? [];
    $totales = $resultado['totales'] ?? [];
    $abiertoTalle = $abierto_talle ?? ($resultado['abierto_talle'] ?? true);
    $incluirCosto = $incluir_costo ?? ($resultado['incluir_costo'] ?? false);
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $colspan = 5 + ($abiertoTalle ? 1 : 0) + ($incluirCosto ? 3 : 0);
    $fmtCant = static function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }
        $dec = abs($v - round($v)) <= 0.0001 ? 0 : 2;
        if ($esExcel && $autoExcelNum) {
            return number_format($v, $dec, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($v, $formatoNumero, $dec);
        }

        return number_format($v, $dec, ',', '.');
    };
    $fmtImp = static function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }
        if ($esExcel && $autoExcelNum) {
            return number_format($v, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($v, $formatoNumero, 2);
        }

        return number_format($v, 2, ',', '.');
    };
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colspan }}" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16px;">{{ $titulo ?? 'Reportes Local — Ventas por artículo' }}</strong></td>
    </tr>
    @if (! empty($subtitulo))
        <tr><td colspan="{{ $colspan }}">{{ $subtitulo }}</td></tr>
    @endif
    <tr><td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td></tr>
    <tr>
        <th>Artículo</th>
        <th>Descripción</th>
        <th>Combinación / color</th>
        @if ($abiertoTalle)
            <th>Talle</th>
        @endif
        <th style="text-align: right;">Cantidad</th>
        @if ($incluirCosto)
            <th style="text-align: right;">P.Vta.</th>
            <th style="text-align: right;">P.Costo</th>
        @endif
        <th style="text-align: right;">Importe venta</th>
        @if ($incluirCosto)
            <th style="text-align: right;">Importe costo</th>
        @endif
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['combinacion_color'] ?? '' }}</td>
            @if ($abiertoTalle)
                <td>{{ $fila['talle'] ?? '' }}</td>
            @endif
            <td style="text-align: right;">{{ $fmtCant($fila['cantidad'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td style="text-align: right;">{{ $fmtImp($fila['precio_venta'] ?? 0) }}</td>
                <td style="text-align: right;">{{ $fmtImp($fila['precio_costo'] ?? 0) }}</td>
            @endif
            <td style="text-align: right;">{{ $fmtImp($fila['importe'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td style="text-align: right;">{{ $fmtImp($fila['importe_costo'] ?? 0) }}</td>
            @endif
        </tr>
    @endforeach
    @if ($filas !== [] && $totales !== [])
        <tr>
            <td colspan="{{ 3 + ($abiertoTalle ? 1 : 0) }}" style="text-align: right; font-weight: bold;">Totales</td>
            <td style="text-align: right; font-weight: bold;">{{ $fmtCant($totales['cantidad'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td></td>
                <td></td>
            @endif
            <td style="text-align: right; font-weight: bold;">{{ $fmtImp($totales['importe'] ?? 0) }}</td>
            @if ($incluirCosto)
                <td style="text-align: right; font-weight: bold;">{{ $fmtImp($totales['importe_costo'] ?? 0) }}</td>
            @endif
        </tr>
    @endif
</table>
