<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría OC Anita {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
<h2 style="margin:0 0 8px 0;">Auditoría órdenes de compra ERP ↔ Anita</h2>
<p style="margin:0 0 16px 0;">
    Período:
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    · Auto-reparar:
    <strong>{{ ! empty($informe['auto_reparar']) ? 'sí' : 'no' }}</strong>
</p>
<p style="margin:0 0 16px 0; color:#555; font-size:13px;">
    Incluye cobertura <code>pendmovp</code> por <code>nro_interno</code>
    (faltantes, o clave repetida en renglones distintos del ERP — no son OC de más).
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;">
        <th align="left">Concepto</th>
        <th align="right">Cantidad</th>
    </tr>
    <tr>
        <td>OC auditadas</td>
        <td align="right">{{ (int) ($informe['total_oc'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>OK</td>
        <td align="right">{{ (int) ($informe['ok'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Reparadas</td>
        <td align="right">{{ (int) ($informe['reparadas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Cobertura pendmovp detectada</td>
        <td align="right">{{ (int) ($informe['pendmovp_cobertura_detectadas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Cobertura pendmovp reparada</td>
        <td align="right">{{ (int) ($informe['pendmovp_cobertura_reparadas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Discrepancias</td>
        <td align="right">{{ count($informe['discrepancias'] ?? []) }}</td>
    </tr>
    <tr>
        <td>Errores</td>
        <td align="right">{{ count($informe['errores'] ?? []) }}</td>
    </tr>
</table>

@if (! empty($informe['filas_reparadas']))
    <h3 style="margin:18px 0 6px 0;">Reparadas automáticamente</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#d5f5e3;">
            <th align="left">OC</th>
            <th align="left">Tipo</th>
            <th align="left">Acciones</th>
        </tr>
        @foreach ($informe['filas_reparadas'] as $fila)
            <tr>
                <td>{{ $fila['numero'] ?? '—' }}</td>
                <td>
                    @if (! empty($fila['pendmovp_cobertura']))
                        pendmovp cobertura
                    @else
                        sync / pad / aplicped
                    @endif
                </td>
                <td>{{ implode('; ', $fila['acciones'] ?? []) }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['discrepancias']))
    <h3 style="margin:18px 0 6px 0;">Discrepancias pendientes</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f0f0f0;">
            <th align="left">OC</th>
            <th align="left">Problemas</th>
            <th align="left">Acciones</th>
        </tr>
        @foreach ($informe['discrepancias'] as $fila)
            <tr>
                <td>{{ $fila['numero'] ?? '—' }}</td>
                <td>{{ implode('; ', $fila['problemas'] ?? []) }}</td>
                <td>{{ implode('; ', $fila['acciones'] ?? []) }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['errores']))
    <h3 style="margin:18px 0 6px 0;">Errores</h3>
    <ul>
        @foreach ($informe['errores'] as $error)
            <li>
                OC {{ $error['numero'] ?? '—' }}:
                {{ $error['mensaje'] ?? '' }}
            </li>
        @endforeach
    </ul>
@endif

<p style="margin-top:20px; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} — anitaERP
    · Comando: <code>php artisan ordencompra:auditoria-anita-diaria</code>
    · Desactivar al dejar Anita: <code>ORDENCOMPRA_AUDITORIA_ANITA_HABILITADA=false</code>
</p>
</body>
</html>
