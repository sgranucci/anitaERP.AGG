<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría asientos COM {{ $informe['fecha_calendario'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría asientos contables — recepción proveedor COM</h2>
<p style="margin:0 0 16px 0;">
    Fecha de recepción auditada:
    <strong>{{ $informe['fecha_calendario'] ?? '—' }}</strong>
    @if (! empty($informe['empresa_id']))
        · Empresa {{ $informe['empresa_id'] }}
    @endif
</p>

<h3 style="margin:18px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#f0f0f0;">
        <th align="left">Concepto</th>
        <th align="right">Cantidad</th>
    </tr>
    <tr>
        <td>COM confirmadas auditadas</td>
        <td align="right">{{ (int) ($informe['total_com'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Sin discrepancia</td>
        <td align="right">{{ (int) ($informe['ok'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Omitidas (contabilidad inactiva)</td>
        <td align="right">{{ (int) ($informe['omitidas'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Con discrepancia</td>
        <td align="right"><strong>{{ count($informe['discrepancias'] ?? []) }}</strong></td>
    </tr>
    <tr>
        <td>Sin auto-reparar (período cerrado)</td>
        <td align="right">{{ (int) ($informe['sin_reparar_periodo_cerrado'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Errores de lectura Anita/ERP</td>
        <td align="right">{{ count($informe['errores_lectura'] ?? []) }}</td>
    </tr>
</table>

@php
    $cerradas = array_values(array_filter(
        $informe['discrepancias'] ?? [],
        static fn ($fila): bool => ! empty($fila['reparacion_omitida_periodo_cerrado'])
    ));
@endphp

@if ($cerradas !== [])
    <h3 style="margin:18px 0 6px 0; color:#b45309;">Período cerrado — revisar a mano (no se modificó nada)</h3>
    <p style="margin:0 0 8px 0; font-size:13px;">
        Hay discrepancias en COM con fecha en período cerrado. La auditoría <strong>no reescribió</strong>
        asientos ERP ni ctamov Anita. Hay que abrir el período (o una apertura) y corregir a mano.
    </p>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
        <tr style="background:#fff8e6;">
            <th align="left">COM</th>
            <th align="left">Fecha</th>
            <th align="left">Empresa</th>
            <th align="left">Asiento ERP</th>
            <th align="left">Aviso</th>
        </tr>
        @foreach ($cerradas as $fila)
            <tr>
                <td>{{ (int) ($fila['com'] ?? 0) }}</td>
                <td>{{ $fila['fecha'] ?? '—' }}</td>
                <td>{{ (int) ($fila['empresa_id'] ?? 0) }}</td>
                <td>{{ $fila['numero_asiento'] ?? '—' }}</td>
                <td>
                    @foreach ($fila['problemas'] ?? [] as $p)
                        @if (str_contains((string) $p, 'Período contable cerrado'))
                            {{ $p }}
                        @endif
                    @endforeach
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['discrepancias']))
    <h3 style="margin:18px 0 6px 0; color:#dc3545;">Discrepancias</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
        <tr style="background:#f0f0f0;">
            <th align="left">COM</th>
            <th align="left">Tipo</th>
            <th align="left">Empresa</th>
            <th align="left">Asiento ERP</th>
            <th align="right">Debe ERP</th>
            <th align="right">Debe Anita</th>
            <th align="left">Problemas</th>
        </tr>
        @foreach ($informe['discrepancias'] as $fila)
            <tr>
                <td>{{ (int) ($fila['com'] ?? 0) }}</td>
                <td>{{ $fila['tipo'] ?? '—' }}</td>
                <td>{{ (int) ($fila['empresa_id'] ?? 0) }}</td>
                <td>{{ $fila['numero_asiento'] ?? '—' }}</td>
                <td align="right">{{ isset($fila['debe_erp']) ? $fmt($fila['debe_erp']) : '—' }}</td>
                <td align="right">{{ isset($fila['debe_anita']) ? $fmt($fila['debe_anita']) : '—' }}</td>
                <td>
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($fila['problemas'] ?? [] as $p)
                            <li>{{ $p }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endforeach
    </table>
@endif

@if (! empty($informe['errores_lectura']))
    <h3 style="margin:18px 0 6px 0; color:#dc3545;">Errores de lectura</h3>
    <ul>
        @foreach ($informe['errores_lectura'] as $err)
            <li>COM {{ (int) ($err['com'] ?? 0) }} (id {{ (int) ($err['recepcion_id'] ?? 0) }}) — {{ $err['mensaje'] ?? '' }}</li>
        @endforeach
    </ul>
@endif

<p style="margin-top:24px; font-size:12px; color:#666;">
    Criterio: recepciones y devoluciones confirmadas con <code>fecha</code> en el alcance, generadas en ERP.<br>
    Auto-reparación: no toca ERP ni Anita si el período (recepción proveedor o asientos) está cerrado; queda en este mail para revisión manual.<br>
    Si Anita editó ctamov después del alta (<code>ctav_fecha_umod</code>) y los montos coinciden, no se pisa Anita: se copian cuentas al ERP.<br>
    Clave Anita COM: tipo + letra + <code>empresa_id</code> (sucursal) + <code>numerorecepcion</code>.<br>
    Valida recepmae, asiento ERP, ctamov, importes, fechas, centros de costo y monedas (sin <code>recm_documentoid</code>).<br>
    Devoluciones: si la recepción origen tiene impuesto interno de cigarrillos, la devolución debe revertirlo (campo + asiento).<br>
    Comando manual:
    <code>php artisan recepcion-proveedor:auditoria-asientos-com --fecha={{ $informe['fecha_calendario'] ?? '' }}</code>
</p>
</body>
</html>
