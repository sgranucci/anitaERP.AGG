<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditor BS.USO Capex {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditor anticipo BS.USO sin Capex (solo ERP)</h2>
<p style="margin:0 0 16px 0;">
    Período (<code>{{ $informe['campo_fecha'] ?? 'fechaiva' }}</code>):
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Cuenta puente <code>{{ $informe['codigo_cuenta'] ?? '123010007' }}</code>
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Asientos ERP con movimiento en anticipo bienes de uso cuya OC no tiene Capex,
    o sin OC vinculada. Se omiten aperturas e importaciones Anita.
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;"><th align="left">Concepto</th><th align="right">Cantidad</th></tr>
    <tr><td>Con movimiento en cuenta puente</td><td align="right">{{ (int) ($informe['total'] ?? 0) }}</td></tr>
    <tr><td>OK (con Capex)</td><td align="right">{{ (int) ($informe['ok'] ?? 0) }}</td></tr>
    <tr><td>Omitidos (apertura / Anita)</td><td align="right">{{ (int) ($informe['omitidos'] ?? 0) }}</td></tr>
    <tr style="background:{{ (int) ($informe['desvios_oc_sin_capex'] ?? 0) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Desvío: OC sin Capex</strong></td>
        <td align="right"><strong>{{ (int) ($informe['desvios_oc_sin_capex'] ?? 0) }}</strong></td>
    </tr>
    <tr style="background:{{ (int) ($informe['desvios_sin_oc'] ?? 0) > 0 ? '#fdebd0' : '#d5f5e3' }};">
        <td>Desvío: sin OC vinculada</td>
        <td align="right">{{ (int) ($informe['desvios_sin_oc'] ?? 0) }}</td>
    </tr>
</table>

@if (! empty($informe['desvios']))
    <h3 style="margin:18px 0 6px 0;">Desvíos ERP</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#fadbd8;">
            <th align="left">Tipo</th>
            <th align="left">Origen</th>
            <th align="left">OC</th>
            <th align="left">Modo</th>
            <th align="right">Monto puente</th>
            <th align="left">Motivo</th>
        </tr>
        @foreach ($informe['desvios'] as $fila)
            <tr>
                <td>{{ ($fila['tipo'] ?? '') === 'sin_oc' ? 'Sin OC' : 'OC sin Capex' }}</td>
                <td>{{ $fila['etiqueta'] ?? '—' }}</td>
                <td>{{ ($fila['oc'] ?? '') !== '' ? $fila['oc'] : '—' }}</td>
                <td>{{ $fila['modo_carga'] ?? '—' }}</td>
                <td align="right">{{ number_format((float) ($fila['monto_puente'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['motivo'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
