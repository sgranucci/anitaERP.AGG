@php
    $esExcel = ! empty($esExcel);
    $datas = $datas ?? collect();
    $subtitulo = 'Generado '.date('d/m/Y H:i').' — '.(is_countable($datas) ? count($datas) : 0).' registro(s)';
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
@endphp
<table>
    @if ($reservarFilaLogoExcel ?? false)
    <tr><td colspan="10" style="height: 52px;">&#160;</td></tr>
    @endif
    <tr>
        <td colspan="10"><strong style="font-size: 16pt;">Listado de comprobantes de proveedor</strong></td>
    </tr>
    <tr>
        <td colspan="10"><strong>{{ $subtitulo }}</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Tipo</th>
            <th>N&uacute;mero</th>
            <th>Fecha</th>
            <th>Total</th>
            <th>Estado</th>
            <th>Origen</th>
            <th>Modo carga</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $row)
        <tr>
            <td>{{ $row->id }}</td>
            <td>{{ $row->empresas->nombre ?? '' }}</td>
            <td>{{ $row->proveedores->nombre ?? '' }}</td>
            <td>{{ $row->tipotransaccion_compras->nombre ?? '' }}</td>
            <td>{{ $row->letra }}{{ $row->sucursal }}-{{ $row->numerocomprobante }}</td>
            <td>{{ $row->fechacomprobante ? $row->fechacomprobante->format('d/m/Y') : '' }}</td>
            <td>{{ $fmtMonto($row->total) }}</td>
            <td>{{ $row->estado }}</td>
            <td>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($row->origen_entrada ?? '') }}</td>
            <td>{{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta($row->modo_carga ?? '') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
