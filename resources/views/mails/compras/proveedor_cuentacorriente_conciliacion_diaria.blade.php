<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conciliación CC proveedores</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Conciliación cuenta corriente de proveedores</h2>
<p style="margin:0 0 16px 0;">
    Ventana de aplicaciones:
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Ficha vs deuda vs mayor es a hoy.
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Controla el descalce histórico de Anita: aplicaciones multi-moneda, ítems de DC abiertos en la ficha
    y diferencias entre el subledger y las cuentas de proveedores MN/ME.
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;">
        <th align="left">Control</th>
        <th align="right">Alertas</th>
    </tr>
    @foreach (($informe['resumen'] ?? []) as $concepto => $cantidad)
        <tr>
            <td>{{ str_replace('_', ' ', $concepto) }}</td>
            <td align="right">{{ (int) $cantidad }}</td>
        </tr>
    @endforeach
</table>

@if (! empty($informe['alertas']))
    <h3 style="margin:18px 0 6px 0;">Detalle</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f8d7da;">
            <th align="left">Tipo</th>
            <th align="left">Detalle</th>
        </tr>
        @foreach ($informe['alertas'] as $alerta)
            <tr>
                <td>{{ $alerta['tipo'] ?? '—' }}</td>
                <td>{{ $alerta['detalle'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

<p style="margin-top:20px; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} — anitaERP
    · <code>php artisan compras:conciliar-cc-proveedor</code>
    · Destinatario: <code>COMPRAS_CC_CONCILIACION_EMAIL</code>
    · Desactivar: <code>COMPRAS_CC_CONCILIACION_HABILITADA=false</code>
</p>
</body>
</html>
