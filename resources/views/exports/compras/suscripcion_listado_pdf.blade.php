@php
    use App\Support\Compras\SuscripcionSupport;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #17202a; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .sub { color: #667; font-size: 9px; margin-bottom: 8px; }
        .kpis { margin-bottom: 8px; }
        .kpis span { display: inline-block; margin-right: 14px; }
        .kpis strong { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85c1e9; text-align: left; padding: 4px; border: 1px solid #b6c6d1; }
        td { padding: 3px 4px; border: 1px solid #d5dbdb; }
        tr:nth-child(even) td { background: #f7f9f9; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>Suscripciones</h1>
    <div class="sub">
        Generado el {{ now()->format('d/m/Y H:i') }}
        @if (! empty($filtros['estado'])) · Estado: {{ SuscripcionSupport::etiquetaEstado($filtros['estado']) }} @endif
        @if (! empty($filtros['area'])) · Área: {{ $filtros['area'] }} @endif
    </div>

    <div class="kpis">
        <span>Vigentes <strong>{{ $kpis['vigentes'] ?? 0 }}</strong></span>
        <span>Pendientes <strong>{{ $kpis['pendientes'] ?? 0 }}</strong></span>
        <span>Vencidas <strong>{{ $kpis['vencidas'] ?? 0 }}</strong></span>
        <span>En desvío <strong>{{ $kpis['desvios'] ?? 0 }}</strong></span>
        <span>Total mensualizado <strong>{{ number_format((float) ($kpis['mensualizado'] ?? 0), 2, ',', '.') }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>OC N°</th>
                <th>Suscripción</th>
                <th>Proveedor</th>
                <th>Área</th>
                <th>CC</th>
                <th>Dueño</th>
                <th>Tarjeta</th>
                <th class="num">Monto</th>
                <th>Period.</th>
                <th class="num">Mensualizado</th>
                <th>Estado</th>
                <th>Vence</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $oc)
                @php
                    $monto = (float) ($oc->suscripcion_monto_periodo ?? 0);
                    $estado = SuscripcionSupport::estadoNegocio($oc);
                @endphp
                <tr>
                    <td>{{ $oc->numeroordencompra }}</td>
                    <td>{{ $oc->suscripcion_nombre ?: $oc->detalle }}</td>
                    <td>{{ optional($oc->proveedores)->nombre }}</td>
                    <td>{{ $oc->suscripcion_area }}</td>
                    <td>{{ optional($oc->centrocostos)->codigo }}</td>
                    <td>{{ optional($oc->suscripcion_owners)->nombre }}</td>
                    <td>••{{ $oc->suscripcion_tarjeta_ult4 }}</td>
                    <td class="num">{{ number_format($monto, 2, ',', '.') }}</td>
                    <td>{{ SuscripcionSupport::etiquetaPeriodicidad($oc->suscripcion_periodicidad) }}</td>
                    <td class="num">{{ number_format(SuscripcionSupport::montoMensualizado($monto, $oc->suscripcion_periodicidad), 2, ',', '.') }}</td>
                    <td>{{ SuscripcionSupport::etiquetaEstado($estado) }}</td>
                    <td>{{ $oc->contrato_vigencia_hasta ? $oc->contrato_vigencia_hasta->format('d/m/Y') : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
