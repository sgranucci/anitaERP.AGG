<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="9" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="9"><strong style="font-size:16pt;">Órdenes de compra — {{ $proveedor->nombre ?? '' }}</strong></td>
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
                {{ (int) $resumen['cantidad'] }} OC ·
                Con factura {{ (int) ($resumen['con_factura'] ?? 0) }} ·
                Monto OC {{ number_format((float) $resumen['monto_oc'], 2, ',', '.') }} ·
                Facturado {{ number_format((float) $resumen['monto_facturado'], 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Nº OC</th>
            <th>Empresa</th>
            <th>Entrega</th>
            <th>Estado</th>
            <th>Monto OC</th>
            <th>Facturado</th>
            <th>Facturas</th>
            <th>Pagos</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $oc)
            @php
                $fmt = static function ($v) {
                    if (! $v) {
                        return '';
                    }
                    if ($v instanceof \Carbon\CarbonInterface) {
                        return $v->format('d/m/Y');
                    }
                    try {
                        return \Illuminate\Support\Carbon::parse($v)->format('d/m/Y');
                    } catch (\Throwable) {
                        return (string) $v;
                    }
                };
            @endphp
            <tr>
                <td>{{ $fmt($oc->fecha) }}</td>
                <td>{{ $oc->numeroordencompra }}</td>
                <td>{{ $oc->empresas->nombre ?? '' }}</td>
                <td>{{ $fmt($oc->fechaentrega) }}</td>
                <td>{{ $oc->estadoordencompra }}</td>
                <td>{{ number_format((float) ($oc->monto_lineas ?? 0), 2, ',', '.') }}</td>
                <td>{{ number_format((float) ($oc->monto_facturado ?? 0), 2, ',', '.') }}</td>
                <td>{{ (int) ($oc->facturas_count ?? 0) }}</td>
                <td>{{ (int) ($oc->pagos_count ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
