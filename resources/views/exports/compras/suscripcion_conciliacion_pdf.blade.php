@php
    use App\Support\Compras\SuscripcionSupport;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14px 18px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #222; }
        h1 { font-size: 12px; margin: 0 0 2px 0; }
        .sub { font-size: 8px; color: #666; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5px solid #999; padding: 2px 3px; }
        thead th { background: #e8f2fa; font-size: 7px; text-transform: uppercase; }
        .r { text-align: right; }
        .c { text-align: center; }
        .kpi { border: 0.5px solid #999; padding: 4px; margin-bottom: 8px; background: #f7f7f7; }
        .kpi span { margin-right: 14px; }
        .desvio { color: #a00; font-weight: bold; }
        .sin { color: #a06000; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Conciliación de tarjeta corporativa — {{ $conciliacion->periodo }}</h1>
    <div class="sub">
        {{ optional($conciliacion->empresas)->nombre }} ·
        período <strong>{{ $conciliacion->estado }}</strong>
        @if ($conciliacion->archivo_nombre)
            · resumen importado: {{ $conciliacion->archivo_nombre }}
        @endif
        · emitido {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="kpi">
        <span>Cargos: <strong>{{ $resumen['cargos'] ?? 0 }}</strong></span>
        <span>Con orden detrás: <strong>{{ number_format((float) ($resumen['cobertura_pct'] ?? 0), 1, ',', '.') }}%</strong></span>
        <span>Conciliados: <strong>{{ $resumen['conciliados'] ?? 0 }}</strong></span>
        <span>En desvío: <strong>{{ $resumen['desvios'] ?? 0 }}</strong></span>
        <span>Sin identificar: <strong>{{ $resumen['sin_identificar'] ?? 0 }}</strong></span>
        <span>A regularizar: <strong>{{ $resumen['a_regularizar'] ?? 0 }}</strong></span>
        <span>Monto: <strong>{{ number_format((float) ($resumen['monto_total'] ?? 0), 2, ',', '.') }}</strong></span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Comercio</th>
                <th class="c">Tarjeta</th>
                <th class="r">Monto</th>
                <th class="c">OC N°</th>
                <th>Suscripción</th>
                <th class="r">Esperado</th>
                <th class="r">Desvío</th>
                <th class="c">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($cargos as $cargo)
                @php
                    $oc = $cargo->ordencompras;
                    $clase = match ($cargo->estado) {
                        \App\Models\Compras\Suscripcion_Cargo::ESTADO_DESVIO => 'desvio',
                        \App\Models\Compras\Suscripcion_Cargo::ESTADO_SIN_IDENTIFICAR,
                        \App\Models\Compras\Suscripcion_Cargo::ESTADO_REGULARIZAR => 'sin',
                        default => '',
                    };
                @endphp
                <tr>
                    <td class="c">{{ $cargo->fecha ? \Carbon\Carbon::parse($cargo->fecha)->format('d/m/Y') : '' }}</td>
                    <td>{{ $cargo->comercio }}</td>
                    <td class="c">{{ $cargo->tarjeta_ult4 ? '••'.$cargo->tarjeta_ult4 : '—' }}</td>
                    <td class="r">{{ number_format((float) $cargo->monto, 2, ',', '.') }}</td>
                    <td class="c">{{ optional($oc)->numeroordencompra ?: '—' }}</td>
                    <td>{{ optional($oc)->suscripcion_nombre ?: '—' }}</td>
                    <td class="r">{{ $oc ? number_format((float) $oc->suscripcion_monto_periodo, 2, ',', '.') : '—' }}</td>
                    <td class="r">{{ $cargo->desvio_pct !== null ? number_format((float) $cargo->desvio_pct, 2, ',', '.').'%' : '—' }}</td>
                    <td class="c {{ $clase }}">{{ SuscripcionSupport::etiquetaEstadoCargo($cargo->estado) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="c">Sin cargos en el período.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
