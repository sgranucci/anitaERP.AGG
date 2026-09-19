<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>I/E caja / cheques / asiento {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Control I/E: caja / cheques ERP ↔ tesmov Anita · asiento ERP ↔ ctamov Anita</h2>
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
        <td>Movimientos I/E</td>
        <td align="right">{{ (int) ($informe['totales']['total_filas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>OK</td>
        <td align="right">{{ (int) ($informe['totales']['ok'] ?? 0) }}</td>
    </tr>
    <tr style="background:{{ ((int) ($informe['totales']['con_desvio'] ?? 0)) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Con desvío</strong></td>
        <td align="right"><strong>{{ (int) ($informe['totales']['con_desvio'] ?? 0) }}</strong></td>
    </tr>
    <tr>
        <td>Sin asiento / tesmov / ctamov / pago</td>
        <td align="right">
            {{ (int) ($informe['totales']['sin_asiento'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_tesmov'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_ctamov'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_pago'] ?? 0) }}
        </td>
    </tr>
    <tr>
        <td>Caja / cheques / asiento / tesmov ($)</td>
        <td align="right">
            {{ number_format((float) ($informe['totales']['caja_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['cheques_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['asiento_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['tesmov_ars'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
</table>

@if (! empty($informe['desvios_mail']))
    <h3 style="margin:18px 0 6px 0;">I/E con desvío</h3>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Empresa</th>
            <th align="left">I/E</th>
            <th align="right">Caja $</th>
            <th align="right">Cheques $</th>
            <th align="right">Asiento $</th>
            <th align="right">tesmov $</th>
            <th align="left">Alertas</th>
        </tr>
        @foreach ($informe['desvios_mail'] as $fila)
            <tr>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['etiqueta'] ?? '' }} {{ $fila['detalle'] ?? '' }}</td>
                <td align="right">{{ number_format((float) ($fila['caja_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['cheques_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['asiento_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['tesmov_ars'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['alertas_texto'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['desvios_omitidos'] ?? 0) > 0)
        <p style="margin:8px 0; color:#555; font-size:12px;">
            Y {{ (int) $informe['desvios_omitidos'] }} I/E más con desvío (no caben en el mail).
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
