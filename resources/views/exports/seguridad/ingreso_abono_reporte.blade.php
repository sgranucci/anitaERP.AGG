<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="9" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="9"><strong>{{ $titulo ?? 'Abono mensual sin ingresos' }}@if(!empty($subtitulo)) — {{ $subtitulo }}@endif</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Proveedor</th>
            <th>Código</th>
            <th>Contrato / Abono</th>
            <th>Empresa</th>
            <th>Estado OC</th>
            <th>Vigencia desde</th>
            <th>Vigencia hasta</th>
            <th>Tickets Finalizado</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['proveedor'] ?? '' }}</td>
                <td>{{ $fila['proveedor_codigo'] ?? '' }}</td>
                <td>{{ $fila['oc_numero'] ?? '' }}</td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['estado_oc'] ?? '' }}</td>
                <td>{{ $fila['vigencia_desde'] ?? '' }}</td>
                <td>{{ $fila['vigencia_hasta'] ?? '' }}</td>
                <td>{{ $fila['tickets_finalizados'] ?? 0 }}</td>
                <td>{{ $fila['resultado'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
