<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="6" style="height:52px;"></td></tr>
    @endif
    <tr>
        <td colspan="6"><strong style="font-size:16pt;">Aging conciliación bancaria</strong></td>
    </tr>
    <tr>
        <td colspan="6">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="6">
            {{ $meta['empresa_nombre'] ?? '' }}
            · {{ $meta['cuentacaja_label'] ?? '' }}
            · {{ sprintf('%02d/%d', (int) ($meta['mes'] ?? 0), (int) ($meta['anio'] ?? 0)) }}
            · Total {{ (int) (($resultado['resumen']['total'] ?? 0)) }}
            · |Importe| {{ number_format((float) ($resultado['resumen']['importe_abs'] ?? 0), 2, '.', '') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Lado</th>
            <th>Fecha</th>
            <th>Días</th>
            <th>Bucket</th>
            <th>Detalle</th>
            <th>Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($resultado['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['lado'] }}</td>
                <td>{{ $fila['fecha'] }}</td>
                <td>{{ $fila['dias'] }}</td>
                <td>{{ $fila['bucket'] }}</td>
                <td>{{ $fila['detalle'] }}</td>
                <td>{{ number_format((float) $fila['importe'], 2, '.', '') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
