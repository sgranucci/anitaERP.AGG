@php
    $columnasDias = $resultado['columnas_dias'] ?? [];
    $filas = $resultado['filas'] ?? [];
    $totalesPorDia = $resultado['totales_por_dia'] ?? [];
    $totalGeneral = (float) ($resultado['total_general'] ?? 0);
    $numCols = 2 + count($columnasDias) + 1;
    $colUltimaIdx = max(0, $numCols - 1);
    $colUltima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colUltimaIdx + 1);
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $numCols }}"></td></tr>
    @endif
    <tr>
        <td colspan="{{ $numCols }}" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'Ventas insumos gastronomía por día' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $numCols }}" style="font-size: 11px;">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr></tr>
    <thead>
        <tr style="background-color: #85C1E9;">
            <th>SKU</th>
            <th>Descripción</th>
            @foreach ($columnasDias as $col)
                <th>{{ $col['label'] }}</th>
            @endforeach
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                @foreach ($columnasDias as $col)
                    @php $cant = (float) ($fila['cantidades_por_dia'][$col['ymd']] ?? 0); @endphp
                    <td>{{ $cant != 0. ? number_format($cant, 3, '.', '') : '' }}</td>
                @endforeach
                <td>{{ number_format((float) ($fila['total'] ?? 0), 3, '.', '') }}</td>
            </tr>
        @endforeach
    </tbody>
    @if (count($filas) > 0)
        <tfoot>
            <tr>
                <td colspan="2" style="font-weight: bold;">Totales</td>
                @foreach ($columnasDias as $col)
                    <td style="font-weight: bold;">{{ number_format((float) ($totalesPorDia[$col['ymd']] ?? 0), 3, '.', '') }}</td>
                @endforeach
                <td style="font-weight: bold;">{{ number_format($totalGeneral, 3, '.', '') }}</td>
            </tr>
        </tfoot>
    @endif
</table>
