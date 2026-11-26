<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; }
        h1 { font-size: 13px; margin: 0 0 2px 0; }
        h2 { font-size: 10px; margin: 12px 0 4px 0; color: #1B4F72; }
        .sub { color: #555; margin-bottom: 8px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 6px; }
        th, td { border: 1px solid #cccccc; padding: 3px 5px; }
        th { background: #85C1E9; color: #17202A; text-align: left; }
        td.r, th.r { text-align: right; }
        tr.tot td { font-weight: bold; background: #f5f5f5; }
        .ok { color: #1E8449; font-weight: bold; }
        .warn { color: #B9770E; font-weight: bold; }
        .aviso { border: 1px solid #F5B041; background: #FEF9E7; padding: 4px 6px; margin: 4px 0; }
    </style>
</head>
<body>
@php
    $fmt = static fn (float $n) => number_format($n, 2, ',', '.');
    $cuadro = $calc['cuadro'];
    $esDj = (bool) $calc['es_dj'];
    $periodoTitulo = ucfirst((string) $calc['periodo_label']);
@endphp

<h1>{{ $titulo }}</h1>
<div class="sub">
    {{ $calc['empresa_label'] }} — {{ $periodoTitulo }}
    — {{ $esDj ? 'DJ mensual (2Q + F.996)' : '1ra quincena (pago a cuenta)' }}
    — Generado {{ now()->format('d/m/Y H:i') }}
</div>

@if (! empty($calc['controles']['avisos']))
    @foreach ($calc['controles']['avisos'] as $aviso)
        <div class="aviso">{{ $aviso }}</div>
    @endforeach
@endif

@if ($esDj)
    <h2>Impuesto determinado (F.996)</h2>
    <table>
        <tr><th>Concepto</th><th class="r">Importe</th></tr>
        <tr><td>Retenciones 1Q ({{ $calc['cantidad_q1'] }} registros)</td><td class="r">{{ $fmt($cuadro['total_q1']) }}</td></tr>
        <tr><td>Retenciones 2Q ({{ $calc['cantidad_q2'] }} registros)</td><td class="r">{{ $fmt($cuadro['total_q2']) }}</td></tr>
        <tr class="tot"><td>Impuesto determinado 353</td><td class="r">{{ $fmt($cuadro['determinado']) }}</td></tr>
        <tr><td>(−) Saldo a favor período anterior</td><td class="r">{{ $fmt($cuadro['saf']) }}</td></tr>
        <tr><td>(−) Pagos a cuenta 1Q{{ $cuadro['pago_cuenta_fecha'] !== '' ? ' ('.\Carbon\Carbon::parse($cuadro['pago_cuenta_fecha'])->format('d/m/Y').')' : '' }}</td><td class="r">{{ $fmt($cuadro['pago_cuenta']) }}</td></tr>
        <tr class="tot"><td>Saldo a favor ARCA (VEP)</td><td class="r">{{ $fmt($cuadro['vep']) }}</td></tr>
    </table>
@else
    <h2>Pago a cuenta 1Q</h2>
    <table>
        <tr><th>Concepto</th><th class="r">Importe</th></tr>
        <tr><td>Retenciones 1Q ({{ $calc['cantidad_q1'] }} registros)</td><td class="r">{{ $fmt($cuadro['total_q1']) }}</td></tr>
        <tr class="tot"><td>Total pago a cuenta (VEP)</td><td class="r">{{ $fmt($cuadro['vep']) }}</td></tr>
    </table>

    <h2>Detalle de retenciones 1Q</h2>
    <table>
        <thead>
            <tr>
                <th>Cert/Nro</th>
                <th>CUIT</th>
                <th>Razón social</th>
                <th>Fecha</th>
                <th>Régimen</th>
                <th>OP</th>
                <th class="r">Base</th>
                <th class="r">Importe</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($calc['registros_q1'] as $reg)
                <tr>
                    <td>{{ $reg['nro_cert'] ?? $reg['nro_comp'] ?? '' }}</td>
                    <td>{{ $reg['nro_documento'] ?? '' }}</td>
                    <td>{{ $reg['razon_social'] ?? '' }}</td>
                    <td>{{ $reg['fecha_retencion'] ?? '' }}</td>
                    <td>{{ $reg['codigo_regimen'] ?? '' }}</td>
                    <td>{{ $reg['nro_comp'] ?? '' }}</td>
                    <td class="r">{{ $fmt((float) ($reg['base_calculo'] ?? 0)) }}</td>
                    <td class="r">{{ $fmt((float) ($reg['importe'] ?? 0)) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Sin retenciones en la 1Q.</td></tr>
            @endforelse
            <tr class="tot">
                <td colspan="7">Total 1Q</td>
                <td class="r">{{ $fmt($cuadro['total_q1']) }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if (! empty($calc['conciliacion']['items']))
    <h2>Conciliación contra el mayor ({{ $calc['periodo_mmyyyy'] }})</h2>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Cuentas</th>
                <th class="r">Total SUSS</th>
                <th class="r">Total mayor</th>
                <th class="r">Diferencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($calc['conciliacion']['items'] as $item)
                <tr>
                    <td>{{ $item['nombre'] ?? '' }}</td>
                    <td>{{ collect($item['cuentas'] ?? [])->map(fn ($c) => trim(($c['codigo'] ?? '').' '.($c['nombre'] ?? '')))->implode(', ') }}</td>
                    <td class="r">{{ $fmt((float) ($item['total_suss'] ?? 0)) }}</td>
                    <td class="r">{{ $fmt((float) ($item['total_mayor'] ?? 0)) }}</td>
                    <td class="r">{{ $fmt((float) ($item['diferencia'] ?? 0)) }}</td>
                    <td>
                        @if (! empty($item['cuadra']))
                            <span class="ok">OK</span>
                        @else
                            <span class="warn">Revisar</span>
                        @endif
                    </td>
                </tr>
                @if (! empty($item['pago_posterior']))
                    <tr>
                        <td colspan="6">
                            Pagos de quincenas anteriores asentados después del corte: $ {{ $fmt((float) $item['pago_posterior']) }} (no se toman como diferencia).
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
