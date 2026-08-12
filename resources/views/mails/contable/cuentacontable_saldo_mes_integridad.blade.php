@php
    $resumen = $informe['resumen'] ?? [];
    $parametros = $informe['parametros'] ?? [];
    $hayDesvio = (int) ($resumen['periodos_con_desvio'] ?? 0) > 0;
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integridad saldos mensuales por cuenta</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Saldos mensuales por cuenta — control de integridad</h2>
<p style="margin:0 0 16px 0;">
    Compara <strong>cuentacontable_saldo_mes</strong> (la tabla que alimenta los balances impresos)
    contra <strong>asiento + asiento_movimiento</strong>.
    Empresas: <strong>{{ ($parametros['empresa_ids'] ?? []) === [] ? 'todas' : implode(', ', $parametros['empresa_ids']) }}</strong>
    · Períodos: <strong>{{ $parametros['periodo_desde'] ?? 'inicio' }} a {{ $parametros['periodo_hasta'] ?? 'fin' }}</strong>
    · Tolerancia: <strong>{{ number_format((float) ($parametros['tolerancia'] ?? 0), 2, ',', '.') }}</strong>
</p>

@if (! $hayDesvio)
    <p style="padding:10px; background:#e8f6ef; border:1px solid #1E8449; margin:0 0 16px 0;">
        <strong>Integridad OK.</strong> El snapshot mensual reproduce los asientos en todas las empresas y períodos controlados.
    </p>
@else
    <p style="padding:10px; background:#fdecea; border:1px solid #922B21; margin:0 0 16px 0;">
        <strong>Integridad rota.</strong>
        {{ (int) ($resumen['empresas_con_desvio'] ?? 0) }} empresa(s) y
        {{ (int) ($resumen['periodos_con_desvio'] ?? 0) }} período(s) con desvío ·
        suma |desvío| {{ number_format((float) ($resumen['suma_abs_desvio'] ?? 0), 2, ',', '.') }}.
        Los balances de esos meses salen mal hasta reconstruir el snapshot
        (<code>php artisan contable:verificar-saldos-cuenta-mes --reparar</code>).
    </p>
@endif

<h3 style="margin:18px 0 6px 0;">Por empresa</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Empresa</th>
        <th align="right">Períodos con desvío</th>
        <th align="right">Suma |desvío|</th>
    </tr>
    @foreach ($informe['empresas'] ?? [] as $empresa)
        <tr>
            <td>{{ $empresa['empresa_id'] }} — {{ $empresa['nombre'] }}</td>
            <td align="right">{{ (int) $empresa['periodos_con_desvio'] }}</td>
            <td align="right">{{ number_format((float) $empresa['suma_abs_desvio'], 2, ',', '.') }}</td>
        </tr>
    @endforeach
</table>

@foreach ($informe['empresas'] ?? [] as $empresa)
    @if ((int) $empresa['periodos_con_desvio'] > 0)
        <h3 style="margin:18px 0 6px 0;">
            Empresa {{ $empresa['empresa_id'] }} — {{ $empresa['nombre'] }}
        </h3>
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
            <tr style="background:#f0f0f0;">
                <th align="left">Período</th>
                <th align="right">Snapshot</th>
                <th align="right">Asientos</th>
                <th align="right">Desvío</th>
                <th align="left">Cuentas que lo explican</th>
            </tr>
            @foreach ($empresa['periodos'] as $periodo)
                <tr>
                    <td>{{ $periodo['periodo'] }}</td>
                    <td align="right">{{ number_format((float) $periodo['snapshot'], 2, ',', '.') }}</td>
                    <td align="right">{{ number_format((float) $periodo['asientos'], 2, ',', '.') }}</td>
                    <td align="right"><strong>{{ number_format((float) $periodo['desvio'], 2, ',', '.') }}</strong></td>
                    <td>
                        @forelse ($periodo['cuentas'] ?? [] as $cuenta)
                            {{ $cuenta['codigo'] }} {{ $cuenta['nombre'] }}
                            ({{ number_format((float) $cuenta['desvio'], 2, ',', '.') }})<br>
                        @empty
                            —
                        @endforelse
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
@endforeach
</body>
</html>
