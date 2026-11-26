@php
    $t = $reporte['totales'] ?? [];
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="12" style="height:52px;"></td></tr>
    @endif
    <tr>
        <td colspan="12"><strong style="font-size:16pt;">Movimientos conciliados</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }} · {{ $meta['empresa_nombre'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="12">
            Cuenta: {{ $meta['cuentacaja_label'] ?? '' }}
            · {{ $meta['periodo_txt'] ?? '' }}
            · {{ (int) ($t['n'] ?? 0) }} cruces
            · Importe {{ number_format((float) ($t['importe'] ?? 0), 2, '.', '') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Conciliado</th>
            <th>Usuario</th>
            <th>Origen</th>
            <th>Cruce</th>
            <th>F. contable</th>
            <th>Comprobante contable</th>
            <th>Detalle contable</th>
            <th>F. banco</th>
            <th>Concepto banco</th>
            <th>Comprobante banco</th>
            <th>Importe</th>
            <th>Diferencia</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($reporte['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['conciliado'] ?? '' }}</td>
                <td>{{ $fila['usuario'] ?? '' }}</td>
                <td>{{ $fila['origen'] ?? '' }}</td>
                <td>{{ $fila['cruce'] ?? '' }}</td>
                <td>{{ $fila['fecha_contable'] ?? '' }}</td>
                <td>{{ $fila['comprobante_contable'] ?? '' }}</td>
                <td>{{ $fila['detalle_contable'] ?? '' }}</td>
                <td>{{ $fila['fecha_banco'] ?? '' }}</td>
                <td>{{ $fila['concepto_banco'] ?? '' }}</td>
                <td>{{ $fila['comprobante_banco'] ?? '' }}</td>
                <td>{{ number_format((float) ($fila['importe'] ?? 0), 2, '.', '') }}</td>
                <td>{{ number_format((float) ($fila['diferencia'] ?? 0), 2, '.', '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="10"><strong>Total general · {{ (int) ($t['n'] ?? 0) }} cruces</strong></td>
            <td>{{ number_format((float) ($t['importe'] ?? 0), 2, '.', '') }}</td>
            <td>{{ number_format((float) ($t['diferencia'] ?? 0), 2, '.', '') }}</td>
        </tr>
        <tr>
            <td colspan="10"><strong>De los cuales contra movimientos de banco · {{ (int) ($t['n_banco'] ?? 0) }} cruces</strong></td>
            <td>{{ number_format((float) ($t['importe_banco'] ?? 0), 2, '.', '') }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="10"><strong>De los cuales cierre Excel (sin movimiento de banco) · {{ (int) ($t['n_cierre'] ?? 0) }} cruces</strong></td>
            <td>{{ number_format((float) ($t['importe_cierre'] ?? 0), 2, '.', '') }}</td>
            <td></td>
        </tr>
    </tbody>
</table>
