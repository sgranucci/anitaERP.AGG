@php
    $filaLogo = $reservarFilaLogoExcel ?? false;
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtMonto = function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $n = (float) $v;
        if ($esExcel && $autoExcelNum) {
            return number_format($n, 2, '.', '');
        }
        if ($esExcel) {
            return \App\Support\Export\ExcelFormatoNumero::formatearTexto($n, $formatoNumero, 2);
        }

        return number_format($n, 2, ',', '.');
    };
    $colspanTotal = 7 + count($resultado['columnas'] ?? []);
@endphp
<table>
    @if ($filaLogo)
        <tr>
            <td colspan="{{ $colspanTotal }}"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspanTotal }}" style="font-weight: bold; font-size: 14px;">{{ $titulo ?? 'IVA COMPRAS' }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspanTotal }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <th>N.Pro.</th>
        <th>Proveedor</th>
        <th>CUIT</th>
        <th>Fec.Comp.</th>
        <th>Fec.Iva</th>
        <th>Tip</th>
        <th>Nro.Comp.</th>
        @foreach ($resultado['columnas'] ?? [] as $col)
            <th>{{ $col['label'] }}</th>
        @endforeach
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
            <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
            <td>{{ $fila['cuit'] ?? '' }}</td>
            <td>{{ $fila['fecha_mov'] ?? '' }}</td>
            <td>{{ $fila['fecha_iva'] ?? '' }}</td>
            <td>{{ $fila['tipo'] ?? '' }}</td>
            <td>{{ $fila['comprobante'] ?? '' }}</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ $fmtMonto($fila['columnas'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endforeach
    @if (! empty($resultado['totales_general']))
        <tr>
            <td colspan="7">TOTAL GENERAL</td>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <td>{{ $fmtMonto($resultado['totales_general'][$col['key']] ?? 0) }}</td>
            @endforeach
        </tr>
    @endif
    @if (! empty($resultado['totales_por_tipo']))
        <tr>
            <td colspan="{{ $colspanTotal }}" style="font-weight: bold;">Por tipo de comprobante</td>
        </tr>
        <tr>
            <th>Tipo</th>
            <th>Nombre</th>
            <th>Comp.</th>
            @foreach ($resultado['columnas'] ?? [] as $col)
                <th>{{ $col['label'] }}</th>
            @endforeach
            @for ($i = 0; $i < 4; $i++)
                <th></th>
            @endfor
        </tr>
        @foreach ($resultado['totales_por_tipo'] as $tot)
            <tr>
                <td>{{ $tot['tipo'] ?? '' }}</td>
                <td>{{ $tot['tipo_nombre'] ?? ($tot['tipo'] ?? '') }}</td>
                <td>{{ (int) ($tot['cantidad'] ?? 0) }}</td>
                @foreach ($resultado['columnas'] ?? [] as $col)
                    <td>{{ $fmtMonto($tot['columnas'][$col['key']] ?? 0) }}</td>
                @endforeach
                @for ($i = 0; $i < 4; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    @endif
</table>
