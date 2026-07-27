@php
    $filas = $resultado['filas'] ?? [];
    $horas = $resultado['horas'] ?? [];
    $cantidadColumnas = (int) ($cantidadColumnas ?? (2 + count($horas) + 2));
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtImporte = static function ($valor) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $valor = (float) $valor;
        if (abs($valor) <= 0.004) {
            return '';
        }
        if ($esExcel && $autoExcelNum) {
            return number_format($valor, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($valor, $formatoNumero, 2);
        }

        return number_format($valor, 2, ',', '.');
    };
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $cantidadColumnas }}" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="{{ $cantidadColumnas }}"><strong style="font-size: 16px;">{{ $titulo ?? 'Venta hora por hora' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $cantidadColumnas }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="{{ $cantidadColumnas }}">{{ $subtitulo ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="{{ $cantidadColumnas }}">
            Jornadas: {{ $resultado['cantidad_dias'] ?? 0 }}
            · Comprobantes: {{ $resultado['cantidad_comprobantes'] ?? 0 }}
            · Horas: {{ $resultado['rango_horas_texto'] ?? '' }}
            · Total: {{ $fmtImporte($resultado['total_general'] ?? 0) }}
            · Promedio por hora: {{ $fmtImporte($resultado['promedio_hora'] ?? 0) }}
        </td>
    </tr>
    <tr>
        <th>Día</th>
        <th>Fecha</th>
        @foreach ($horas as $hora)
            <th style="text-align: right;">{{ str_pad((string) $hora, 2, '0', STR_PAD_LEFT) }}</th>
        @endforeach
        <th style="text-align: right;">Total</th>
        <th style="text-align: right;">Promedio</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['dia'] ?? '' }}</td>
            <td>{{ ! empty($fila['fecha']) ? \Illuminate\Support\Carbon::parse($fila['fecha'])->format('d/m/Y') : '' }}</td>
            @foreach ($horas as $hora)
                <td style="text-align: right;">{{ $fmtImporte($fila['importes'][$hora] ?? 0) }}</td>
            @endforeach
            <td style="text-align: right;">{{ $fmtImporte($fila['total'] ?? 0) }}</td>
            <td style="text-align: right;">{{ $fmtImporte($fila['promedio'] ?? 0) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="2" style="text-align: right;"><strong>Total general</strong></td>
        @foreach ($horas as $hora)
            <td style="text-align: right;"><strong>{{ $fmtImporte($resultado['totales_hora'][$hora] ?? 0) }}</strong></td>
        @endforeach
        <td style="text-align: right;"><strong>{{ $fmtImporte($resultado['total_general'] ?? 0) }}</strong></td>
        <td style="text-align: right;"><strong>{{ $fmtImporte($resultado['promedio_hora'] ?? 0) }}</strong></td>
    </tr>
</table>
