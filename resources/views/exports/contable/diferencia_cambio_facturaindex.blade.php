<table>
    <thead>
        <tr>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Factura</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Proveedor</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">OC</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Recepción</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">TC recepción</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">TC factura</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Nominal</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Pesos</th>
            <th style="background:#85C1E9;color:#17202A;font-weight:bold;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $cp = $fila->comprobantes; @endphp
            <tr>
                <td>{{ $cp ? $cp->letra.' '.$cp->sucursal.'-'.$cp->numerocomprobante : '' }}</td>
                <td>{{ optional(optional($cp)->proveedores)->nombre }}</td>
                <td>{{ optional(optional($cp)->ordencompras)->numeroordencompra }}</td>
                <td>{{ $fila->recepcion_proveedor_id }}</td>
                <td>{{ number_format((float) $fila->tc_recepcion, 4, '.', '') }}</td>
                <td>{{ number_format((float) $fila->tc_factura, 4, '.', '') }}</td>
                <td>{{ number_format((float) $fila->nominal_me, 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila->importe_pesos, 2, '.', '') }}</td>
                <td>{{ $fila->estado }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
