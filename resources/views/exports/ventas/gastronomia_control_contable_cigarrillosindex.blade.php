@php
    use App\Support\Export\ExcelFormatoNumero;

    $columnas = $resultado['columnas_dias'] ?? [];
    $productos = $resultado['productos'] ?? [];
    $cuentaTabaco = $resultado['cuenta_tabaco_codigo'] ?? '414020001';
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($autoExcelNum) {
            return number_format($n, 2, '.', '');
        }

        return ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
    };
    $fmtCant = function ($v) use ($formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if (abs($n) < 0.0001) {
            return '';
        }
        if ($autoExcelNum) {
            return number_format($n, 0, '.', '');
        }

        return ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 0);
    };
    $conceptos = [
        'pcio_vta' => ['label' => 'Pcio VTA', 'estilo' => 'normal', 'fmt' => 'monto'],
        'imp_interno_unit' => ['label' => '% Imp Interno', 'estilo' => 'normal', 'fmt' => 'monto'],
        'cantidad' => ['label' => 'Cantidad vend', 'estilo' => 'celeste', 'fmt' => 'cant'],
        'venta_total' => ['label' => 'VENTA total/Caja', 'estilo' => 'rojo', 'fmt' => 'monto'],
        'imp_interno' => ['label' => 'IMP INTERNO', 'estilo' => 'rojo', 'fmt' => 'monto'],
        'gravado' => ['label' => 'Gravado', 'estilo' => 'rojo', 'fmt' => 'monto'],
        'neto' => ['label' => 'NETO', 'estilo' => 'rojo', 'fmt' => 'monto'],
        'iva' => ['label' => 'IVA', 'estilo' => 'rojo', 'fmt' => 'monto'],
        'redondeo' => ['label' => 'REDONDEO', 'estilo' => 'rojo', 'fmt' => 'monto'],
    ];
    $styleCeleste = 'background-color: #DDEBF7;';
    $styleRojo = 'color: #C00000;';
    $styleAmarillo = 'background-color: #FFFF00;';
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ 2 + max(1, count($columnas)) }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ 2 + max(1, count($columnas)) }}" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'Control contable cigarrillos' }}</td>
    </tr>
    @if (($subtitulo ?? '') !== '')
        <tr>
            <td colspan="{{ 2 + max(1, count($columnas)) }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td style="font-weight: bold; {{ $styleCeleste }}">TIPO CIG</td>
        <td style="font-weight: bold; {{ $styleCeleste }}">CONCEPTOS</td>
        @foreach ($columnas as $col)
            <td style="font-weight: bold; text-align: center; {{ $styleCeleste }}">{{ $col['label'] ?? '' }}</td>
        @endforeach
    </tr>

    @foreach ($productos as $producto)
        <tr>
            <td colspan="{{ 2 + max(1, count($columnas)) }}" style="font-weight: bold;">{{ $producto['descripcion'] ?? '' }}</td>
        </tr>
        @foreach ($conceptos as $clave => $meta)
            @php
                $estilo = $meta['estilo'];
                $styleFila = $estilo === 'celeste' ? $styleCeleste : ($estilo === 'rojo' ? $styleRojo : '');
            @endphp
            <tr>
                @if ($clave === 'pcio_vta')
                    <td>{{ $producto['sku'] ?? '' }}</td>
                @else
                    <td></td>
                @endif
                <td style="{{ $styleFila }}">{{ $meta['label'] }}</td>
                @foreach ($columnas as $col)
                    @php
                        $celda = $producto['por_dia'][$col['ymd']][$clave] ?? 0;
                        $texto = $meta['fmt'] === 'cant' ? $fmtCant($celda) : $fmtMonto($celda);
                    @endphp
                    <td style="text-align: right; {{ $styleFila }}">{{ $texto }}</td>
                @endforeach
            </tr>
        @endforeach
    @endforeach

    <tr><td colspan="{{ 2 + max(1, count($columnas)) }}"></td></tr>
    <tr>
        <td></td>
        <td style="font-weight: bold;">Sumatoria(imp interno+ neto)</td>
        @foreach ($columnas as $col)
            <td style="text-align: right; font-weight: bold;">{{ $fmtMonto($resultado['sumatoria_por_dia'][$col['ymd']] ?? 0) }}</td>
        @endforeach
    </tr>
    <tr>
        <td></td>
        <td style="font-weight: bold;">Mayor {{ $cuentaTabaco }}</td>
        @foreach ($columnas as $col)
            <td style="text-align: right;">{{ $fmtMonto($resultado['mayor_por_dia'][$col['ymd']] ?? 0) }}</td>
        @endforeach
    </tr>
    <tr>
        <td></td>
        <td style="font-weight: bold;">Diferencias</td>
        @foreach ($columnas as $col)
            <td style="text-align: right; {{ $styleAmarillo }}">{{ $fmtMonto($resultado['diferencia_por_dia'][$col['ymd']] ?? 0) }}</td>
        @endforeach
    </tr>
</table>
