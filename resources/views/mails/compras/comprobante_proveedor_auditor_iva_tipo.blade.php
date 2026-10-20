<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditor IVA tipo {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditor Contaduría P3 — IVA fuera de tipo (solo ERP)</h2>
<p style="margin:0 0 16px 0;">
    Período (<code>{{ $informe['campo_fecha'] ?? 'fechaiva' }}</code>):
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Detecta conceptos IVA (tipoconcepto I) con monto que no están en el maestro del tipo
    (FIS/FIB/FGA/FNS…). Omite tipos prorrateados (FPB/FPS/…).
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;"><th align="left">Concepto</th><th align="right">Cantidad</th></tr>
    <tr><td>Comprobantes en alcance</td><td align="right">{{ (int) ($informe['total'] ?? 0) }}</td></tr>
    <tr><td>OK</td><td align="right">{{ (int) ($informe['ok'] ?? 0) }}</td></tr>
    <tr><td>Omitidos (prorrateo / sin tipo)</td><td align="right">{{ (int) ($informe['omitidos'] ?? 0) }}</td></tr>
    <tr style="background:{{ count($informe['desvios'] ?? []) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Desvíos</strong></td>
        <td align="right"><strong>{{ count($informe['desvios'] ?? []) }}</strong></td>
    </tr>
    <tr><td>Errores</td><td align="right">{{ count($informe['errores'] ?? []) }}</td></tr>
</table>

@if (! empty($informe['desvios']))
    <h3 style="margin:18px 0 6px 0;">Desvíos</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#fadbd8;">
            <th align="left">Comprobante</th>
            <th align="left">Tipo</th>
            <th align="left">Estado</th>
            <th align="left">Conceptos ajenos</th>
        </tr>
        @foreach ($informe['desvios'] as $fila)
            <tr>
                <td>{{ $fila['etiqueta'] ?? '—' }}</td>
                <td>{{ $fila['abreviatura'] ?? '—' }}</td>
                <td>{{ $fila['estado_cp'] ?? '—' }}</td>
                <td>{{ implode('; ', $fila['conceptos_fuera'] ?? []) }}</td>
            </tr>
        @endforeach
    </table>
@endif
</body>
</html>
