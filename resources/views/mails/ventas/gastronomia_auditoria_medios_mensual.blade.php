<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría por medio de cobro {{ $fechaDesde }} → {{ $fechaHasta }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $fmtDiff = static function ($n) {
        if ($n === null) { return '—'; }
        $s = number_format((float) $n, 2, ',', '.');
        return (float) $n > 0 ? '+'.$s : $s;
    };
    $etiquetaFuente = static function ($f) {
        return match ((string) $f) {
            'informe_z' => 'Informe Z',
            'cobranza_erp' => 'Cobranza ERP',
            'fondo_fijo' => 'Fondo fijo',
            default => $f !== '' ? $f : '—',
        };
    };
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría por medio de cobro — Z ↔ contabilizado (ERP)</h2>
<p style="margin:0 0 12px 0;">
    Período (jornadas del mes hasta hoy − {{ (int) config('gastronomia.auditoria_medios_mensual.dias_atras', 2) }} días):
    <strong>{{ $fechaDesde }}</strong> → <strong>{{ $fechaHasta }}</strong>
    · Tolerancia $ {{ $fmt($tolerancia) }}
</p>
<p style="margin:0 0 12px 0; color:#555; font-size:13px;">
    Columna Z (esperado): <strong>Informe Z</strong> (tótem/MP) + <strong>cobranzas ERP</strong> (efectivo/tarjeta) + <strong>fondo fijo</strong> (compensación proceso).
    Adjunto CSV: totales del mes (<code>tipo=mes</code>) y detalle día × medio (<code>tipo=dia</code>).
</p>

@if ($hayDiferencias)
    <p style="color:#dc3545; font-weight:bold;">Hay jornadas con diferencia Z ↔ contabilizado por medio de cobro.</p>
@else
    <p style="color:#28a745; font-weight:bold;">Sin diferencias por medio de cobro en el mes (dentro de tolerancia).</p>
@endif

@foreach ($resumen as $emp)
    @php
        $porCuenta = [];
        foreach ($emp['z_por_medio'] ?? [] as $m) {
            $cod = (string) ($m['cuenta_codigo'] ?? '');
            if ($cod === '') { continue; }
            $porCuenta[$cod] = [
                'codigo' => $cod,
                'nombre' => (string) ($m['cuenta_nombre'] ?? ''),
                'fuente' => (string) ($m['fuente'] ?? ''),
                'z' => (float) ($m['total'] ?? 0),
                'contab' => 0.0,
            ];
        }
        foreach ($emp['contabilizado_por_medio'] ?? [] as $m) {
            $cod = (string) ($m['cuenta_codigo'] ?? '');
            if ($cod === '') { continue; }
            if (! isset($porCuenta[$cod])) {
                $porCuenta[$cod] = ['codigo' => $cod, 'nombre' => (string) ($m['cuenta_nombre'] ?? ''), 'fuente' => '', 'z' => 0.0, 'contab' => 0.0];
            }
            $porCuenta[$cod]['contab'] = (float) ($m['total'] ?? 0);
        }
        ksort($porCuenta);
        $estadoEmp = (string) ($emp['estado'] ?? 'OK');
    @endphp

    <h3 style="margin:18px 0 6px 0;">
        Empresa {{ $emp['empresa_id'] }} — {{ $emp['empresa_nombre'] }}
        <span style="font-weight:normal; color:#666;">({{ $emp['jornadas'] ?? 0 }} jornadas)</span>
        @if ($estadoEmp === 'DIF')
            <span style="color:#dc3545;">· DIF</span>
        @else
            <span style="color:#28a745;">· OK</span>
        @endif
    </h3>

    <p style="margin:0 0 8px 0; color:#444;">
        Venta ERP (neto): <strong>$ {{ $fmt($emp['venta_erp'] ?? 0) }}</strong>
        · Contabilidad global: <strong>$ {{ $fmt($emp['contabilidad_global'] ?? 0) }}</strong>
        (Δ ERP↔contab $ {{ $fmtDiff($emp['diff_erp_contabilidad'] ?? 0) }})
        · Flash (rendgastro): <strong>$ {{ $fmt($emp['flash'] ?? 0) }}</strong>
        · Total Z medios: <strong>$ {{ $fmt($emp['total_z'] ?? 0) }}</strong>
        · Total contab medios: <strong>$ {{ $fmt($emp['total_contabilizado'] ?? 0) }}</strong>
    </p>

    <p style="margin:8px 0 4px 0; font-weight:bold;">Totales del mes por medio</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; margin-bottom:8px;">
        <thead>
            <tr style="background:#85C1E9; color:#17202A;">
                <th align="left" style="border:1px solid #ccc;">Cuenta</th>
                <th align="left" style="border:1px solid #ccc;">Nombre</th>
                <th align="left" style="border:1px solid #ccc;">Fuente Z</th>
                <th align="right" style="border:1px solid #ccc;">Z (mes)</th>
                <th align="right" style="border:1px solid #ccc;">Contabilizado (mes)</th>
                <th align="right" style="border:1px solid #ccc;">Δ</th>
                <th align="center" style="border:1px solid #ccc;">Estado</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($porCuenta as $c)
            @php
                $diff = round($c['z'] - $c['contab'], 2);
                $esDif = abs($diff) > (float) $tolerancia;
                $estado = $esDif ? 'DIF' : 'OK';
            @endphp
            <tr style="background: {{ $esDif ? '#fdecea' : '#ffffff' }};">
                <td style="border:1px solid #ccc;">{{ $c['codigo'] }}</td>
                <td style="border:1px solid #ccc;">{{ $c['nombre'] }}</td>
                <td style="border:1px solid #ccc;">{{ $etiquetaFuente($c['fuente']) }}</td>
                <td align="right" style="border:1px solid #ccc;">$ {{ $fmt($c['z']) }}</td>
                <td align="right" style="border:1px solid #ccc;">$ {{ $fmt($c['contab']) }}</td>
                <td align="right" style="border:1px solid #ccc; {{ $esDif ? 'color:#dc3545; font-weight:bold;' : '' }}">{{ $fmtDiff($diff) }}</td>
                <td align="center" style="border:1px solid #ccc; color: {{ $estado === 'DIF' ? '#dc3545' : '#28a745' }};">{{ $estado }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @php $diasDif = $emp['jornadas_dif_medio'] ?? []; @endphp
    @if ($diasDif !== [])
        <p style="margin:6px 0 4px 0; color:#dc3545; font-weight:bold;">Jornadas con DIF ({{ count($diasDif) }}) — detalle día × medio en el CSV adjunto:</p>
        <ul style="margin:0 0 10px 18px; color:#dc3545;">
            @foreach ($diasDif as $j)
                <li>{{ $j['fecha_jornada'] ?? '' }} · Δ total $ {{ $fmtDiff($j['diff_total'] ?? 0) }}</li>
            @endforeach
        </ul>
    @endif
@endforeach

<p style="margin:16px 0 0 0; color:#888; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} · anitaERP · Adjunto CSV: totales del mes (<code>tipo=mes</code>) y detalle día × medio (<code>tipo=dia</code>).
</p>
</body>
</html>
