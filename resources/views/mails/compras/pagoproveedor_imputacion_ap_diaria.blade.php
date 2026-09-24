<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CC / asiento / promov / ctamov {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Control OP a OP: CC ERP / asiento ERP / promov Anita / ctamov Anita</h2>
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
        <td>Órdenes de pago</td>
        <td align="right">{{ (int) ($informe['totales']['total_filas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>OK</td>
        <td align="right">{{ (int) ($informe['totales']['ok'] ?? 0) }}</td>
    </tr>
    <tr style="background:{{ ((int) ($informe['totales']['en_borrador'] ?? 0)) > 0 ? '#fcf3cf' : '#fff' }};">
        <td>En pre carga (sin confirmar)</td>
        <td align="right">{{ (int) ($informe['totales']['en_borrador'] ?? 0) }}</td>
    </tr>
    <tr style="background:{{ ((int) ($informe['totales']['cabecera_anita'] ?? 0)) > 0 ? '#e8daef' : '#fff' }};">
        <td>Solo documento Anita (sin CC/asiento ERP)</td>
        <td align="right">{{ (int) ($informe['totales']['cabecera_anita'] ?? 0) }}</td>
    </tr>
    <tr style="background:{{ ((int) ($informe['totales']['con_desvio'] ?? 0)) > 0 ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Con desvío</strong></td>
        <td align="right"><strong>{{ (int) ($informe['totales']['con_desvio'] ?? 0) }}</strong></td>
    </tr>
    <tr>
        <td>Sin CC / asiento / promov / ctamov</td>
        <td align="right">
            {{ (int) ($informe['totales']['sin_cc'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_asiento'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_promov'] ?? 0) }}
            /
            {{ (int) ($informe['totales']['sin_ctamov'] ?? 0) }}
        </td>
    </tr>
    <tr>
        <td>CC / asiento / promov / ctamov ($)</td>
        <td align="right">
            {{ number_format((float) ($informe['totales']['cc_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['asiento_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['promov_ars'] ?? 0), 2, ',', '.') }}
            /
            {{ number_format((float) ($informe['totales']['ctamov_ars'] ?? 0), 2, ',', '.') }}
        </td>
    </tr>
</table>

@if (! empty($informe['borradores_mail']))
    <h3 style="margin:18px 0 6px 0;">OP en pre carga</h3>
    <p style="margin:0 0 8px 0; color:#555; font-size:12px;">
        Todavía no se confirmaron: no se exigen CC, asiento, promov ni ctamov. No son un error de cuadre.
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f9e79f; color:#17202A;">
            <th align="left">Empresa</th>
            <th align="left">OP</th>
            <th align="left">Proveedor</th>
            <th align="left">Fecha</th>
            <th align="right">Total $</th>
            <th align="left">Estado</th>
        </tr>
        @foreach ($informe['borradores_mail'] as $fila)
            <tr>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['etiqueta'] ?? '' }}</td>
                <td>{{ $fila['nombre_proveedor'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td align="right">{{ number_format((float) ($fila['total_origen'] ?? 0), 2, ',', '.') }}</td>
                <td>{{ $fila['estado'] ?? 'PRE CARGA' }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['borradores_omitidos'] ?? 0) > 0)
        <p style="margin:8px 0; color:#555; font-size:12px;">
            Y {{ (int) $informe['borradores_omitidos'] }} OP más en pre carga (no caben en el mail).
        </p>
    @endif
@endif

@if (! empty($informe['cabeceras_anita_mail']))
    <h3 style="margin:18px 0 6px 0;">Solo documento Anita</h3>
    <p style="margin:0 0 8px 0; color:#555; font-size:12px;">
        Cabeceras importadas sin cuenta corriente ni asiento ERP. No son desvío: la contabilidad vive en Anita.
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#d7bde2; color:#17202A;">
            <th align="left">Empresa</th>
            <th align="left">OP</th>
            <th align="left">Proveedor</th>
            <th align="left">Fecha</th>
            <th align="right">Total $</th>
            <th align="right">promov $</th>
        </tr>
        @foreach ($informe['cabeceras_anita_mail'] as $fila)
            <tr>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['etiqueta'] ?? '' }}</td>
                <td>{{ $fila['nombre_proveedor'] ?? '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td align="right">{{ number_format((float) ($fila['total_origen'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['promov_ars'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['cabeceras_anita_omitidas'] ?? 0) > 0)
        <p style="margin:8px 0; color:#555; font-size:12px;">
            Y {{ (int) $informe['cabeceras_anita_omitidas'] }} cabeceras más (no caben en el mail).
        </p>
    @endif
@endif

@if (! empty($informe['desvios_mail']))
    <h3 style="margin:18px 0 6px 0;">OP con desvío</h3>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Empresa</th>
            <th align="left">OP</th>
            <th align="right">CC $</th>
            <th align="right">Asiento $</th>
            <th align="right">promov $</th>
            <th align="right">ctamov $</th>
            <th align="right">CC − asiento</th>
            <th align="left">Alertas</th>
        </tr>
        @foreach ($informe['desvios_mail'] as $fila)
            <tr>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                <td>{{ $fila['etiqueta'] ?? '' }}</td>
                <td align="right">{{ number_format((float) ($fila['cc_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['asiento_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['promov_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format((float) ($fila['ctamov_ars'] ?? 0), 2, ',', '.') }}</td>
                <td align="right">{{ number_format(round((float) ($fila['cc_ars'] ?? 0) - (float) ($fila['asiento_ars'] ?? 0), 2), 2, ',', '.') }}</td>
                <td>{{ $fila['alertas_texto'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['desvios_omitidos'] ?? 0) > 0)
        <p style="margin:8px 0; color:#555; font-size:12px;">
            Y {{ (int) $informe['desvios_omitidos'] }} OP más con desvío (no caben en el mail).
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
