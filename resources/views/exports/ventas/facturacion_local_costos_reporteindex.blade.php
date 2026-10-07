@php
    $filas = $resultado['filas'] ?? [];
    $esExcel = ! empty($esExcel);
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtImp = static function ($v) use ($esExcel, $formatoNumero, $autoExcelNum) {
        $v = (float) $v;
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
        <tr><td colspan="8" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="8"><strong style="font-size: 16px;">{{ $titulo ?? 'Reporte de costos del local' }}</strong></td>
    </tr>
    <tr>
        <td colspan="8">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr><td colspan="8">{{ $subtitulo }}</td></tr>
    @endif
    @if (! empty($mostrar_aviso))
        <tr>
            <td colspan="8">Hay {{ (int) ($resultado['sin_precio'] ?? 0) }} SKU con precio de fábrica en 0. El costo local queda en 0.</td>
        </tr>
    @endif
    <tr>
        <th>SKU</th>
        <th>Descripción</th>
        <th>Marca</th>
        <th>Canal</th>
        <th>Estado</th>
        <th>Precio fábrica</th>
        <th>Costo local</th>
        <th>Aviso</th>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['sku'] ?? '' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $fila['marca'] ?? '' }}</td>
            <td>{{ $fila['canal'] ?? '' }}</td>
            <td>{{ $fila['estado'] ?? '' }}</td>
            <td>{{ $fmtImp($fila['precio_fabrica'] ?? 0) }}</td>
            <td>{{ $fmtImp($fila['costo'] ?? 0) }}</td>
            <td>{{ ! empty($fila['sin_precio']) ? 'Precio en 0' : '' }}</td>
        </tr>
    @endforeach
</table>
