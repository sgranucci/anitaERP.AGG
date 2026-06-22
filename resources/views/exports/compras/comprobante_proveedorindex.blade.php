<table>
    @if ($reservarFilaLogoExcel ?? false)
    <tr><td colspan="10"></td></tr>
    @endif
    <tr>
        <td colspan="10"><strong>Listado de comprobantes de proveedor</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Tipo</th>
            <th>Número</th>
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
            <td>{{ number_format((float) $row->total, 2, ',', '.') }}</td>
            <td>{{ $row->estado }}</td>
            <td>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($row->origen_entrada ?? '') }}</td>
            <td>{{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta($row->modo_carga ?? '') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
