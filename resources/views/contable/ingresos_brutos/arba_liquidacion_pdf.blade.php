<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación ARBA {{ $liquidacion->nombre_txt }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 13px; margin: 0 0 2px 0; }
        h2 { font-size: 10px; margin: 12px 0 4px 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #cccccc; padding: 3px 4px; vertical-align: top; }
        thead th { background-color: #85C1E9; color: #17202A; text-align: left; }
        tr:nth-child(even) td { background-color: #f5f5f5; }
        .r { text-align: right; }
        .ok { color: #1E8449; font-weight: bold; }
        .fail { color: #C0392B; font-weight: bold; }
        .aviso { background-color: #FCF3CF; }
    </style>
</head>
<body>
@php
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
    $t = $calc['totales'];
    $control = $calc['control'];
    $periodo = substr((string) $calc['periodo'], 4, 2).'/'.substr((string) $calc['periodo'], 0, 4);
    $quincena = ((int) $calc['quincena']) === 1 ? '1.ª' : '2.ª';
@endphp

<h1>Liquidación ARBA — Retenciones IIBB</h1>
<div class="muted">
    {{ $empresa }} — {{ $quincena }} quincena {{ $periodo }}
    — Archivo TXT: {{ $liquidacion->nombre_txt }}
    — Generado {{ now()->format('d/m/Y H:i') }}
</div>

<h2>Resumen</h2>
<table>
    <tbody>
        <tr><td>Retenciones ERP (listado)</td><td class="r">{{ $m($t['erp']) }}</td></tr>
        <tr><td>Neteo de la quincena</td><td class="r">{{ $m($t['neteo_quincena']) }}</td></tr>
        <tr><td>Neteo de quincenas anteriores</td><td class="r">{{ $m($t['neteo_anteriores']) }}</td></tr>
        <tr><td><strong>TXT ARBA</strong></td><td class="r"><strong>{{ $m($t['txt']) }}</strong></td></tr>
        <tr><td>Pendientes de la quincena (sin neteo)</td><td class="r">{{ $m($t['pendientes_quincena']) }}</td></tr>
        <tr><td>Pendientes de quincenas anteriores</td><td class="r">{{ $m($t['pendientes_anteriores']) }}</td></tr>
        <tr><td><strong>DJ (suma de líneas truncadas)</strong></td><td class="r"><strong>{{ $m($t['dj']) }}</strong></td></tr>
        <tr><td><strong>VEP (DJ truncada a 10 centavos)</strong></td><td class="r"><strong>{{ $m($t['vep']) }}</strong></td></tr>
    </tbody>
</table>

<h2>Conciliación contra el mayor</h2>
<table>
    <tbody>
        <tr><td>Movimiento del período en el mayor</td><td class="r">{{ $t['movimiento_mayor'] !== null ? $m($t['movimiento_mayor']) : '—' }}</td></tr>
        <tr><td>Residuo período (mayor − TXT + pendientes − neteos anteriores)</td><td class="r">{{ $control['residuo_periodo'] !== null ? $m($control['residuo_periodo']) : '—' }}</td></tr>
        <tr><td>Saldo del mayor al hasta</td><td class="r">{{ $t['saldo_mayor'] !== null ? $m($t['saldo_mayor']) : '—' }}</td></tr>
        <tr><td>Residuo saldo (saldo mayor − TXT + pendientes)</td><td class="r">{{ $control['residuo_pendientes'] !== null ? $m($control['residuo_pendientes']) : '—' }}</td></tr>
        <tr>
            <td>Tolerancia</td>
            <td class="r {{ $control['ok'] ? 'ok' : 'fail' }}">
                {{ $control['ok'] ? 'CONCILIA' : 'NO CONCILIA' }} (± {{ $m($control['tolerancia']) }})
            </td>
        </tr>
    </tbody>
</table>

<h2>Devoluciones del período</h2>
<table>
    <thead>
        <tr>
            <th>Asiento</th><th>Fecha</th><th>Detalle</th><th>Proveedor</th>
            <th class="r">Importe</th><th class="r">Neteado</th><th class="r">Pendiente</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($calc['devoluciones'] as $f)
            <tr>
                <td>{{ $f['asiento_numero'] }}</td>
                <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $f['detalle'] }}</td>
                <td>{{ $f['proveedor_label'] !== '' ? $f['proveedor_label'] : 'Sin identificar' }}</td>
                <td class="r">{{ $m($f['importe']) }}</td>
                <td class="r">{{ $m($f['neteado'] ?? 0) }}</td>
                <td class="r">{{ $m($f['pendiente'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin devoluciones en la quincena.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Neteos aplicados</h2>
<table>
    <thead>
        <tr><th>Certificado</th><th>Devolución</th><th class="r">Importe</th></tr>
    </thead>
    <tbody>
        @forelse ($calc['aplicaciones'] as $ap)
            <tr>
                <td>{{ $ap['nro_cert'] }}</td>
                <td>{{ $ap['etiqueta'] ?? $ap['devolucion_id'] }}</td>
                <td class="r">{{ $m($ap['importe']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No hubo neteos en esta liquidación.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Pendientes sin neteo</h2>
<table>
    <thead>
        <tr><th>Origen</th><th>Asiento</th><th>Fecha</th><th>Proveedor</th><th class="r">Saldo</th><th class="r">Antigüedad (días)</th></tr>
    </thead>
    <tbody>
        @forelse ($calc['pendientes'] as $p)
            <tr class="{{ ! empty($p['aviso']) ? 'aviso' : '' }}">
                <td>{{ ($p['origen'] ?? '') === 'anterior' ? 'Quincena anterior' : 'Esta quincena' }}</td>
                <td>{{ $p['asiento_numero'] ?? '' }}</td>
                <td>{{ \Carbon\Carbon::parse($p['fecha'])->format('d/m/Y') }}</td>
                <td>{{ $p['proveedor_label'] ?? '' }}</td>
                <td class="r">{{ $m($p['pendiente']) }}</td>
                <td class="r">{{ $p['antiguedad_dias'] ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin pendientes.</td></tr>
        @endforelse
    </tbody>
</table>

<h2>Certificados ajustados</h2>
<table>
    <thead>
        <tr>
            <th>Certificado</th><th>CUIT</th><th>Razón social</th>
            <th class="r">Original</th><th class="r">Neteado</th><th class="r">TXT</th><th class="r">Base TXT</th><th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @php $ajustados = array_filter($calc['certificados'], static fn ($c) => $c['estado'] !== 'sin_cambio'); @endphp
        @forelse ($ajustados as $c)
            <tr>
                <td>{{ $c['nro_cert'] }}</td>
                <td>{{ $c['nro_documento'] }}</td>
                <td>{{ $c['razon_social'] }}</td>
                <td class="r">{{ $m($c['importe_original']) }}</td>
                <td class="r">{{ $m($c['neteado']) }}</td>
                <td class="r">{{ $m($c['importe_nuevo']) }}</td>
                <td class="r">{{ $m($c['base_txt']) }}</td>
                <td>{{ $c['estado'] === 'eliminado' ? 'Sale del TXT' : 'Neteo parcial' }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Ningún certificado fue ajustado: el TXT es el listado original.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
