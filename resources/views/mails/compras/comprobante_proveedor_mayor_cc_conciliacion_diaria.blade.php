<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mayor vs CC proveedores {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Mayor Anita proveedores vs cuenta corriente</h2>
<p style="margin:0 0 16px 0;">
    Período:
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Empresa ERP:
    <strong>{{ (int) ($informe['empresa_id'] ?? 0) }}</strong>
    · Anita:
    <strong>{{ (int) ($informe['empresa_anita'] ?? 0) }}</strong>
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Cuentas:
    MN <code>{{ (int) ($informe['cuenta_mn'] ?? 0) }}</code>
    /
    ME <code>{{ (int) ($informe['cuenta_me'] ?? 0) }}</code>.
    Mayor leído desde Anita (subdiario + ctamov). CC solo facturas ERP.
</p>

<h3 style="margin:18px 0 6px 0;">Totales</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Concepto</th>
        <th align="right">Moneda nacional</th>
        <th align="right">Moneda extranjera ($)</th>
    </tr>
    <tr>
        <td>Mayor Anita (neto Haber)</td>
        <td align="right">{{ number_format((float) ($informe['mayor_mn'] ?? 0), 2, ',', '.') }}</td>
        <td align="right">{{ number_format((float) ($informe['mayor_me'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td>CC facturas ERP</td>
        <td align="right">{{ number_format((float) ($informe['cc_mn'] ?? 0), 2, ',', '.') }}</td>
        <td align="right">{{ number_format((float) ($informe['cc_me'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    <tr style="background:{{ ! empty($informe['requiere_alerta']) ? '#fadbd8' : '#d5f5e3' }};">
        <td><strong>Diferencia (mayor − CC)</strong></td>
        <td align="right"><strong>{{ number_format((float) ($informe['diferencia_mn'] ?? 0), 2, ',', '.') }}</strong></td>
        <td align="right"><strong>{{ number_format((float) ($informe['diferencia_me'] ?? 0), 2, ',', '.') }}</strong></td>
    </tr>
    <tr>
        <td>Movimientos mayor / facturas CC</td>
        <td align="right">{{ (int) ($informe['movimientos_mayor_mn'] ?? 0) }} / {{ (int) ($informe['facturas_cc_mn'] ?? 0) }}</td>
        <td align="right">{{ (int) ($informe['movimientos_mayor_me'] ?? 0) }} / {{ (int) ($informe['facturas_cc_me'] ?? 0) }}</td>
    </tr>
</table>

@if ((float) ($informe['cc_me_moneda_origen'] ?? 0) != 0.0)
    <p style="margin:12px 0; font-size:13px;">
        CC ME en moneda origen:
        <strong>{{ number_format((float) $informe['cc_me_moneda_origen'], 2, ',', '.') }}</strong>
        (convertida a $ con cotización de cada factura).
    </p>
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
