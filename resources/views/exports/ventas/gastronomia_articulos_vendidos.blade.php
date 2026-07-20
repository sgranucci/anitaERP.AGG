@php
    $esExcel = ! empty($esExcel);
    $filas = $filas ?? collect();
    $subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($filas) ? count($filas) : 0).' registro(s)';
    $formatoNumero = $formatoNumero ?? \App\Support\Export\ExcelFormatoNumero::preferenciaGlobal();
    $autoExcelNum = \App\Support\Export\ExcelFormatoNumero::esAuto($formatoNumero);
    $fmtNum = function ($v, $dec = 2) use ($esExcel, $formatoNumero, $autoExcelNum) {
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
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="9" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="9"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Artículos vendidos — gastronomía</h2></td>
        </tr>
        <tr>
            <td colspan="9"><strong>{{ $subtitulo }}</strong></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Subcategoría</th>
            <th>Punto de venta</th>
            <th>Depósito</th>
            <th>Cantidad</th>
            <th>Importe</th>
            <th>Comprob.</th>
            <th>Artículo ID</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->sku ?? '—' }}</td>
                <td>{{ $f->descripcion ?? '—' }}</td>
                <td>{{ trim((string) ($f->subcategoria_nombre ?? '')) !== '' ? $f->subcategoria_nombre : '—' }}</td>
                <td>{{ $f->puntoventa_etiqueta !== '' ? $f->puntoventa_etiqueta : '—' }}</td>
                <td>{{ $f->deposito_etiqueta !== '' ? $f->deposito_etiqueta : '—' }}</td>
                <td>{{ $fmtNum($f->cantidad_total ?? 0, 3) }}</td>
                <td>{{ $fmtNum($f->importe_total ?? 0, 2) }}</td>
                <td>{{ (int) ($f->cantidad_comprobantes ?? 0) }}</td>
                <td>{{ (int) ($f->articulo_id ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
