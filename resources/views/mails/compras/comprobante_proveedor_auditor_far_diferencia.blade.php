<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditor FAR vs OC {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditor Contaduría P1 — FAR vs cuentas OC (solo ERP)</h2>
<p style="margin:0 0 16px 0;">
    Período (<code>{{ $informe['campo_fecha'] ?? 'fechaiva' }}</code>):
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Cuenta FAR <code>{{ $informe['codigo_far'] ?? '211010004' }}</code>
    · Tol. $ {{ number_format((float) ($informe['tolerancia'] ?? 0), 2, ',', '.') }}
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Detecta facturas contra COM donde el FAR del asiento no coincide con el que armaría hoy el motor
    (la diferencia FC vs COM debe ir a cuentas de artículos/OC, no a provisión).
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;"><th align="left">Concepto</th><th align="right">Cantidad</th></tr>
    <tr><td>Facturas auditadas</td><td align="right">{{ (int) ($informe['total'] ?? 0) }}</td></tr>
    <tr><td>OK</td><td align="right">{{ (int) ($informe['ok'] ?? 0) }}</td></tr>
    <tr style="background:{{ count($informe['desvios'] ?? []) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Desvíos</strong></td>
        <td align="right"><strong>{{ count($informe['desvios'] ?? []) }}</strong></td>
    </tr>
    <tr><td>Errores</td><td align="right">{{ count($informe['errores'] ?? []) }}</td></tr>
</table>

@if (! empty($informe['desvios']))
    <h3 style="margin:18px 0 6px 0;">Desvíos</h3>
    @if (! empty($informe['desvios_truncados']))
        <p style="color:#a00; font-size:12px;">Listado truncado en el correo.</p>
    @endif
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#fadbd8;">
            <th align="left">Factura</th>
            <th align="left">OC</th>
            <th align="right">FAR asiento</th>
            <th align="right">FAR esperado</th>
            <th align="right">Dif. esperada→OC</th>
            <th align="left">Motivo</th>
        </tr>
        @foreach ($informe['desvios'] as $fila)
            <tr>
                <td>{{ $fila['etiqueta'] ?? '—' }}</td>
                <td>{{ $fila['oc'] ?? '—' }}</td>
                <td align="right">{{ number_format((float) ($fila['far_asiento'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['far_esperado'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['far_diferencia_esperada'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['motivo'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['errores']))
    <h3 style="margin:18px 0 6px 0;">Errores</h3>
    <ul>
        @foreach ($informe['errores'] as $err)
            <li>{{ $err['etiqueta'] ?? '#' }} — {{ $err['mensaje'] ?? '' }}</li>
        @endforeach
    </ul>
@endif
</body>
</html>
