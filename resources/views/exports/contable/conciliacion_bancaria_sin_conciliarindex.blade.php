@php
    $hasta = $meta['fecha_hasta'] ?? '';
    try {
        $hastaTxt = $hasta !== '' ? \Carbon\Carbon::parse($hasta)->format('d/m/y') : '';
    } catch (\Throwable) {
        $hastaTxt = (string) $hasta;
    }
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr><td colspan="12" style="height:52px;"></td></tr>
    @endif
    <tr>
        <td colspan="12"><strong style="font-size:16pt;">Movimientos de Cuentas sin conciliar</strong></td>
    </tr>
    <tr>
        <td colspan="12">Generado {{ date('d/m/Y H:i') }} · {{ $meta['empresa_nombre'] ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="12">
            Desde primer mov. hasta {{ $hastaTxt }}
            · Cuenta: {{ $meta['cuentacaja_label'] ?? ($reporte['meta']['cuentacaja_label'] ?? '') }}
            · {{ (int) (($reporte['totales']['n'] ?? 0)) }} mov.
            · Débitos {{ number_format((float) ($reporte['totales']['debitos'] ?? 0), 2, '.', '') }}
            · Créditos {{ number_format((float) ($reporte['totales']['creditos'] ?? 0), 2, '.', '') }}
        </td>
    </tr>
    <thead>
        <tr>
            <th>Tip</th>
            <th>Numero</th>
            <th>N.Orig.</th>
            <th>F.Mov.</th>
            <th>F.Dev.</th>
            <th>F.Entr.</th>
            <th>F.Conc.</th>
            <th>Detalle</th>
            <th>Debitos</th>
            <th>Creditos</th>
            <th>N.Con.</th>
            <th>Detalle del concepto</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($reporte['filas'] ?? []) as $fila)
            <tr>
                <td>{{ $fila['tip'] ?? '' }}</td>
                <td>{{ $fila['numero'] ?? '' }}</td>
                <td>{{ $fila['n_orig'] ?? '' }}</td>
                <td>{{ $fila['f_mov'] ?? '' }}</td>
                <td>{{ $fila['f_dev'] ?? '' }}</td>
                <td>{{ $fila['f_entr'] ?? '' }}</td>
                <td>{{ $fila['f_conc'] ?? '' }}</td>
                <td>{{ $fila['detalle'] ?? '' }}</td>
                <td>{{ abs((float) ($fila['debito'] ?? 0)) > 0.005 ? number_format((float) $fila['debito'], 2, '.', '') : '' }}</td>
                <td>{{ abs((float) ($fila['credito'] ?? 0)) > 0.005 ? number_format((float) $fila['credito'], 2, '.', '') : '' }}</td>
                <td>{{ $fila['n_con'] ?? '' }}</td>
                <td>{{ $fila['detalle_concepto'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
