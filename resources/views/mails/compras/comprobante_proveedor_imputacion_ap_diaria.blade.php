<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CC / asiento / ctamov {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Control factura a factura: CC ERP / asiento ERP / ctamov Anita</h2>
<p style="margin:0 0 16px 0;">
    Período:
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Empresas:
    <strong>{{ implode(', ', $informe['empresa_ids'] ?? []) }}</strong>
    · Tolerancia $
    <strong>{{ number_format((float) ($informe['tolerancia'] ?? 0), 2, ',', '.') }}</strong>
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Concepto</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Facturas</td>
        <td align="right">{{ (int) ($informe['totales']['total_filas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>OK</td>
        <td align="right">{{ (int) ($informe['totales']['ok'] ?? 0) }}</td>
    </tr>
    <tr style="background:{{ ! empty($informe['requiere_alerta']) ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Con desvío</strong></td>
        <td align="right"><strong>{{ (int) ($informe['totales']['con_desvio'] ?? 0) }}</strong></td>
    </tr>
    <tr>
        <td>Sin CC / sin asiento / sin ctamov</td>
        <td align="right">
            {{ (int) ($informe['totales']['sin_cc'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_asiento'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_ctamov'] ?? 0) }}
        </td>
    </tr>
    <tr>
        <td>CC / asiento / ctamov ($)</td>
        <td align="right">
            {{ number_format((float) ($informe['totales']['cc_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['asiento_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['ctamov_ars'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
</table>

@if (! empty($informe['desvios_mail']))
    <h3 style="margin:18px 0 6px 0;">Facturas con desvío</h3>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Empresa</th>
            <th align="left">Comprobante</th>
            <th align="right">CC $</th>
            <th align="right">Asiento $</th>
            <th align="right">ctamov $</th>
            <th align="right">CC − asiento</th>
            <th align="right">Asiento − ctamov</th>
            <th align="left">Alertas</th>
        </tr>
        @foreach ($informe['desvios_mail'] as $fila)
            <tr>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['comprobante_etiqueta'] ?? '' }}</td>
                <td align="right">{{ number_format((float) ($fila['cc_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['asiento_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['ctamov_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['diff_cc_asiento'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['diff_asiento_ctamov'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['alertas_texto'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['desvios_omitidos'] ?? 0) > 0)
        <p style="margin:8px 0; color:#555; font-size:12px;">
            Y {{ (int) $informe['desvios_omitidos'] }} factura(s) más con desvío (no caben en el mail).
        </p>
    @endif
@endif

@if (! empty($informe['notas']))
    <h3 style="margin:18px 0 6px 0;">Notas</h3>
    <ul>
        @foreach ($informe['notas'] as $nota)
            <li>{{ $nota }}</li>
        @endforeach
    </ul>
@endif

@if (! empty($informe['errores']))
    <h3 style="margin:18px 0 6px 0;">Errores</h3>
    <ul>
        @foreach ($informe['errores'] as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
</body>
</html>
