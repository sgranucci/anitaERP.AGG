@php
    use App\Support\Compras\SuscripcionSupport;
@endphp
<table>
    <thead>
        <tr>
            <th colspan="11">
                Conciliación {{ $conciliacion->periodo }} —
                {{ optional($conciliacion->empresas)->nombre }} —
                {{ $conciliacion->estado }} —
                emitido {{ now()->format('d/m/Y H:i') }}
            </th>
        </tr>
        <tr>
            <th>Fecha</th>
            <th>Comercio</th>
            <th>Comercio normalizado</th>
            <th>Tarjeta</th>
            <th>Monto</th>
            <th>Moneda</th>
            <th>OC N°</th>
            <th>Suscripción</th>
            <th>Monto esperado</th>
            <th>Desvío %</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($cargos as $cargo)
            @php $oc = $cargo->ordencompras; @endphp
            <tr>
                <td>{{ $cargo->fecha ? \Carbon\Carbon::parse($cargo->fecha)->format('d/m/Y') : '' }}</td>
                <td>{{ $cargo->comercio }}</td>
                <td>{{ $cargo->comercio_normalizado }}</td>
                <td>{{ $cargo->tarjeta_ult4 ? '••'.$cargo->tarjeta_ult4 : '' }}</td>
                <td>{{ number_format((float) $cargo->monto, 2, ',', '.') }}</td>
                <td>{{ optional($cargo->monedas)->nombre }}</td>
                <td>{{ optional($oc)->numeroordencompra }}</td>
                <td>{{ optional($oc)->suscripcion_nombre }}</td>
                <td>{{ $oc ? number_format((float) $oc->suscripcion_monto_periodo, 2, ',', '.') : '' }}</td>
                <td>{{ $cargo->desvio_pct !== null ? number_format((float) $cargo->desvio_pct, 2, ',', '.') : '' }}</td>
                <td>{{ SuscripcionSupport::etiquetaEstadoCargo($cargo->estado) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><td colspan="11"></td></tr>
        <tr>
            <th colspan="2">Cargos</th>
            <td>{{ $resumen['cargos'] ?? 0 }}</td>
            <th colspan="2">Cobertura con orden</th>
            <td>{{ number_format((float) ($resumen['cobertura_pct'] ?? 0), 1, ',', '.') }}%</td>
            <th colspan="2">Monto del período</th>
            <td colspan="3">{{ number_format((float) ($resumen['monto_total'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        <tr>
            <th colspan="2">Conciliados</th>
            <td>{{ $resumen['conciliados'] ?? 0 }}</td>
            <th colspan="2">En desvío</th>
            <td>{{ $resumen['desvios'] ?? 0 }}</td>
            <th colspan="2">Sin identificar</th>
            <td colspan="3">{{ $resumen['sin_identificar'] ?? 0 }}</td>
        </tr>
    </tfoot>
</table>
