@php
    $esExcel = ! empty($esExcel);
    $filas = $filas ?? collect();
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
    $periodoTxt = '';
    if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
        $periodoTxt = 'Período: '.($filtros['fecha_desde'] ?? '—');
        if (($filtros['fecha_hasta'] ?? '') !== ($filtros['fecha_desde'] ?? '')) {
            $periodoTxt .= ' → '.($filtros['fecha_hasta'] ?? '—');
        }
    }
    $subtitulo = trim('Generado '.date('d/m/Y H:i').' — '.(is_countable($filas) ? count($filas) : 0).' registro(s)'.($periodoTxt !== '' ? ' · '.$periodoTxt : ''));
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="13" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="13">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado canjes marketing</h2>
            </td>
        </tr>
        <tr>
            <td colspan="13"><strong>{{ $subtitulo }}</strong></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Id VIP</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Nickname</th>
            <th>Mozo</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>CMV</th>
            <th>P. venta</th>
            <th>Sala</th>
            <th>SKU</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $f)
            <tr>
                <td>{{ $f->fechacanje_fmt ?? '—' }}</td>
                <td>{{ $f->nombreempresa ?? '—' }}</td>
                <td>{{ $f->numeroid_vip ?? '—' }}</td>
                <td>{{ $f->nombre_vip ?? '—' }}</td>
                <td>{{ $f->apellido_vip ?? '—' }}</td>
                <td>{{ $f->nickname ?? '' }}</td>
                <td>{{ $f->mozo_etiqueta !== '' ? $f->mozo_etiqueta : '—' }}</td>
                <td>{{ $f->producto ?? '—' }}</td>
                <td>{{ $fmtNum($f->cantidad ?? 0, 3) }}</td>
                <td>{{ $fmtNum($f->cmv ?? 0, 2) }}</td>
                <td>{{ $fmtNum($f->precio_venta ?? 0, 2) }}</td>
                <td>{{ $f->sala !== '' ? $f->sala : '—' }}</td>
                <td>{{ $f->sku ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
    @if (! empty($totales))
        <tfoot>
            <tr>
                <td colspan="8"><strong>Totales</strong></td>
                <td>{{ $fmtNum($totales['cantidad_total'] ?? 0, 3) }}</td>
                <td>{{ $fmtNum($totales['cmv_total'] ?? 0, 2) }}</td>
                <td>{{ $fmtNum($totales['precio_venta_total'] ?? 0, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>
