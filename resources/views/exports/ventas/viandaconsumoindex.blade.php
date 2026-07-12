<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="11" style="height: 52px;"></td></tr>
    @endif
    <tr><td colspan="11"><strong>{{ $titulo ?? 'Reporte de viandas' }}</strong></td></tr>
    <tr><td colspan="11">Generado {{ date('d/m/Y H:i') }}</td></tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr><td colspan="11">{{ $subtitulo }}</td></tr>
    @endif
    <tr><td colspan="11">Consumos: {{ (int) ($totales['consumos'] ?? 0) }} · Ítems: {{ (int) ($totales['items'] ?? 0) }} · Costo: {{ number_format((float) ($totales['costo'] ?? 0), 2, ',', '.') }} · Venta: {{ number_format((float) ($totales['venta'] ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td colspan="11">Filas: {{ $filas->count() }}</td></tr>
    <thead>
        <tr>
            <th>Código</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Login</th>
            <th>Empleado</th>
            <th>Centro de costo</th>
            <th>Empresa</th>
            <th>Ítems</th>
            <th>Costo</th>
            <th>Venta</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila->codigo_retiro }}</td>
                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                <td>{{ $fila->hora }}</td>
                <td>{{ $fila->login_usuario }}</td>
                <td>{{ $fila->nombre_usuario }}</td>
                <td>{{ optional($fila->centrocosto)->nombre }}</td>
                <td>{{ optional($fila->empresa)->nombre }}</td>
                <td>{{ (int) $fila->cantidad_items }}</td>
                <td>{{ number_format((float) $fila->total_costo, 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila->total_venta, 2, '.', '') }}</td>
                <td>{{ $fila->etiquetaEstado() }}</td>
            </tr>
        @endforeach

        @if (count($resumen_centrocosto ?? []) > 0)
            <tr><td colspan="11"></td></tr>
            <tr><td colspan="11"><strong>Resumen por centro de costo</strong></td></tr>
            <tr>
                <td><strong>Centro de costo</strong></td>
                <td colspan="6"></td>
                <td><strong>Consumos</strong></td>
                <td><strong>Ítems</strong></td>
                <td><strong>Costo</strong></td>
                <td><strong>Venta</strong></td>
            </tr>
            @foreach ($resumen_centrocosto as $r)
                <tr>
                    <td>{{ $r['centrocosto'] }}</td>
                    <td colspan="6"></td>
                    <td>{{ (int) $r['consumos'] }}</td>
                    <td>{{ (int) $r['items'] }}</td>
                    <td>{{ number_format((float) $r['costo'], 2, '.', '') }}</td>
                    <td>{{ number_format((float) $r['venta'], 2, '.', '') }}</td>
                </tr>
            @endforeach
        @endif
    </tbody>
</table>
