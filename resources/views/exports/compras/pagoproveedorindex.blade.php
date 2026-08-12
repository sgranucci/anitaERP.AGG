<table>
@if (!empty($reservarFilaLogoExcel))
    <tr><td colspan="7" style="height:52px;"></td></tr>
@endif
    <tr>
        <td colspan="7"><strong style="font-size:16pt;">Órdenes de pago a proveedores</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>OP</th>
            <th>Empresa</th>
            <th>Proveedor</th>
            <th>Monto</th>
            <th>Estado</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $fila)
            <tr>
                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                <td>{{ $fila->etiquetaComprobante() }}</td>
                <td>{{ $fila->empresas->nombre ?? '' }}</td>
                <td>{{ $fila->proveedores->nombre ?? '' }}</td>
                <td>{{ number_format((float)$fila->monto, 2, ',', '.') }}</td>
                <td>{{ $fila->estado }}</td>
                <td>{{ $fila->detalle }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
