<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="9" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="9"><strong style="font-size:16pt;">Pagos del proveedor — {{ $proveedor->nombre ?? '' }}</strong></td>
    </tr>
    <tr>
        <td colspan="9">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="9">{{ $subtitulo ?? '' }}</td>
    </tr>
    @if (($resumen['cantidad'] ?? 0) > 0)
        <tr>
            <td colspan="9">
                {{ (int) $resumen['cantidad'] }} pagos ·
                Monto {{ number_format((float) $resumen['monto_pagado'], 2, ',', '.') }} ·
                Retenciones {{ number_format((float) $resumen['monto_retenciones'], 2, ',', '.') }} ·
                Neto {{ number_format((float) $resumen['monto_neto'], 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Orden de pago</th>
            <th>Empresa</th>
            <th>Monto</th>
            <th>Retenciones</th>
            <th>Neto</th>
            <th>Moneda</th>
            <th>Estado</th>
            <th>Cert.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $pago)
            @php
                $ret = (float) ($pago->total_retenciones ?? 0);
                $neto = (float) $pago->monto - $ret;
            @endphp
            <tr>
                <td>{{ optional($pago->fecha)->format('d/m/Y') }}</td>
                <td>{{ $pago->etiquetaComprobante() }}</td>
                <td>{{ $pago->empresas->nombre ?? '' }}</td>
                <td>{{ number_format((float) $pago->monto, 2, ',', '.') }}</td>
                <td>{{ number_format($ret, 2, ',', '.') }}</td>
                <td>{{ number_format($neto, 2, ',', '.') }}</td>
                <td>{{ $pago->monedas->abreviatura ?? '' }}</td>
                <td>{{ $pago->estado }}</td>
                <td>{{ (int) ($pago->pagoproveedor_retenciones_count ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
