<table>
    <tr>
        <td colspan="13"><strong>{{ $titulo ?? 'Cruce visitas por OC' }}@if(!empty($subtitulo)) — {{ $subtitulo }}@endif</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Fecha</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Cód. prov.</th>
            <th>Proveedor / Visitante</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>OC</th>
            <th>Requisición</th>
            <th>COM</th>
            <th>Factura</th>
            <th>Orden de pago</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['ticket_id'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                <td>{{ $fila['proveedor_nombre'] ?? '' }}</td>
                <td>{{ $fila['motivo'] ?? '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>{{ $fila['oc_etiqueta'] ?: ($fila['oc_numero'] ?? '') }}</td>
                <td>{{ $fila['requisicion'] ?? '' }}</td>
                <td>{{ $fila['coms'] ?? '' }}</td>
                <td>{{ $fila['facturas'] ?? '' }}</td>
                <td>{{ $fila['ordenes_pago'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
