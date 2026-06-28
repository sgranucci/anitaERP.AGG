<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="11" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="11"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Rendiciones m&aacute;quinas vending</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>N&ordm; cierre (empresa)</th>
            <th>Fecha rendici&oacute;n</th>
            <th>Fecha jornada</th>
            <th>Empresa</th>
            <th>M&aacute;quina</th>
            <th>PV</th>
            <th>Total ventas</th>
            <th>Total cobrado</th>
            <th>Caja</th>
            <th>Anita</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $fila)
            <tr>
                <td>{{ (int) $fila->numero_cierre }}</td>
                <td>{{ $fila->fecha_rendicion?->format('d/m/Y H:i') }}</td>
                <td>{{ $fila->fecha_jornada?->format('d/m/Y') }}</td>
                <td>{{ $fila->nombreempresa }}</td>
                <td>{{ $fila->maquina_nombre }}</td>
                <td>{{ $fila->puntoventa_codigo }}</td>
                <td>{{ number_format((float) $fila->total_ventas, 2, '.', '') }}</td>
                <td>{{ number_format((float) $fila->total_cobrado, 2, '.', '') }}</td>
                <td>{{ $fila->rendicionCaja ? 'Presentada' : 'Pendiente' }}</td>
                <td>{{ $fila->anita_sincronizado_en ? 'OK' : '—' }}</td>
                <td>{{ optional($fila->usuario)->nombre }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
