@php
    use App\Support\Compras\SuscripcionSupport;
@endphp
<table>
    <thead>
        <tr>
            <th colspan="13">Suscripciones — {{ now()->format('d/m/Y H:i') }}</th>
        </tr>
        <tr>
            <th>OC N°</th>
            <th>Suscripción</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Área</th>
            <th>Centro de costo</th>
            <th>Cuenta contable</th>
            <th>Dueño del servicio</th>
            <th>Tarjeta</th>
            <th>Monto período</th>
            <th>Periodicidad</th>
            <th>Mensualizado</th>
            <th>Tope autorizado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $oc)
            @php
                $tolerancia = (float) ($oc->suscripcion_tolerancia_pct ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT);
                $monto = (float) ($oc->suscripcion_monto_periodo ?? 0);
            @endphp
            <tr>
                <td>{{ $oc->numeroordencompra }}</td>
                <td>{{ $oc->suscripcion_nombre ?: $oc->detalle }}</td>
                <td>{{ optional($oc->proveedores)->nombre }}</td>
                <td>{{ optional($oc->empresas)->nombre }}</td>
                <td>{{ $oc->suscripcion_area }}</td>
                <td>{{ trim((optional($oc->centrocostos)->codigo ?? '').' '.(optional($oc->centrocostos)->nombre ?? '')) }}</td>
                <td>{{ trim((optional($oc->contrato_cuentacontables)->codigo ?? '').' '.(optional($oc->contrato_cuentacontables)->nombre ?? '')) }}</td>
                <td>{{ optional($oc->suscripcion_owners)->nombre }}</td>
                <td>••{{ $oc->suscripcion_tarjeta_ult4 }}</td>
                <td>{{ number_format($monto, 2, ',', '.') }}</td>
                <td>{{ SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad) }}</td>
                <td>{{ number_format(SuscripcionSupport::montoMensualizado($monto, $oc->suscripcion_periodicidad), 2, ',', '.') }}</td>
                <td>{{ number_format(SuscripcionSupport::topeAutorizado($monto, $tolerancia), 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
