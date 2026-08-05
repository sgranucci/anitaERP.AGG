@php
    $colspan = 8;
    $totalDebe = 0.0;
    $totalHaber = 0.0;
    foreach ($data->asiento_movimientos as $movimiento) {
        if ($movimiento->monto >= 0) {
            $totalDebe += (float) $movimiento->monto;
        } else {
            $totalHaber += abs((float) $movimiento->monto);
        }
    }
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong>Asiento contable N&deg; {{ $data->numeroasiento }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Empresa: {{ optional($data->empresas)->nombre ?? '—' }}
                | Tipo: {{ optional($data->tipoasientos)->nombre ?? '—' }}
                | Fecha: {{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}
                | Generado por: {{ optional($data->usuarios)->nombre ?? optional($data->usuarios)->usuario ?? '—' }}
                | ID: {{ $data->id }}
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}">
                Observaciones: {{ $data->observacion !== null && $data->observacion !== '' ? $data->observacion : '—' }}
            </td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>C&oacute;digo</th>
            <th>Cuenta</th>
            <th>Centro de costo</th>
            <th>Moneda</th>
            <th>Debe</th>
            <th>Haber</th>
            <th>Cotizaci&oacute;n</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data->asiento_movimientos as $movimiento)
            <tr>
                <td>{{ optional($movimiento->cuentacontables)->codigo ?? '' }}</td>
                <td>{{ optional($movimiento->cuentacontables)->nombre ?? '' }}</td>
                <td>{{ optional($movimiento->centrocostos)->nombre ?? '' }}</td>
                <td>{{ optional($movimiento->monedas)->abreviatura ?? optional($movimiento->monedas)->nombre ?? '' }}</td>
                <td>
                    @if ($movimiento->monto >= 0)
                        {{ (float) $movimiento->monto }}
                    @endif
                </td>
                <td>
                    @if ($movimiento->monto < 0)
                        {{ abs((float) $movimiento->monto) }}
                    @endif
                </td>
                <td>{{ (float) $movimiento->cotizacion }}</td>
                <td>{{ $movimiento->observacion ?? '' }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4"><strong>Totales</strong></td>
            <td><strong>{{ $totalDebe }}</strong></td>
            <td><strong>{{ $totalHaber }}</strong></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>
