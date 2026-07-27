<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="10" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="10"><strong style="font-size:16pt;">Retenciones del proveedor — {{ $proveedor->nombre ?? '' }}</strong></td>
    </tr>
    <tr>
        <td colspan="10">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="10">{{ $subtitulo ?? '' }}</td>
    </tr>
    @if (($resumen['cantidad_retenciones'] ?? 0) > 0)
        <tr>
            <td colspan="10">
                {{ (int) $resumen['cantidad_retenciones'] }} certificados ·
                Importe {{ number_format((float) $resumen['monto_retenciones'], 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Fecha</th>
            <th>OP</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Certificado</th>
            <th>Base</th>
            <th>Alícuota</th>
            <th>Importe</th>
            <th>Provincia</th>
            <th>Régimen</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $ret)
            @php $pago = $ret->pagoproveedores; @endphp
            <tr>
                <td>{{ optional(optional($pago)->fecha)->format('d/m/Y') }}</td>
                <td>{{ $pago ? $pago->etiquetaComprobante() : '' }}</td>
                <td>{{ optional(optional($pago)->empresas)->nombre }}</td>
                <td>{{ $ret->etiquetaTipo() }}</td>
                <td>{{ $ret->nro_certificado ?: '' }}</td>
                <td>{{ number_format((float) $ret->base_calculo, 2, ',', '.') }}</td>
                <td>{{ number_format((float) $ret->alicuota, 4, ',', '.') }}</td>
                <td>{{ number_format((float) $ret->importe, 2, ',', '.') }}</td>
                <td>{{ optional($ret->provincias)->nombre }}</td>
                <td>{{ $ret->codigo_regimen }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
