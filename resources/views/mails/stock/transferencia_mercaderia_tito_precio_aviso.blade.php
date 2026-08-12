<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRA TITO precio promedio {{ $informe['codigo'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $fmt6 = static fn ($n) => number_format((float) $n, 6, ',', '.');
@endphp

<h2 style="margin:0 0 8px 0;">Monitoreo precio promedio TITO — TRCONT</h2>
<p style="margin:0 0 16px 0;">
    Se contabilizó una transferencia con artículos TITO
    (<code>fl_precio_promedio_transferencia</code>). Detalle del promedio usado en el asiento.
</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; margin-bottom:16px;">
    <tr style="background:#f0f0f0;">
        <th align="left">Campo</th>
        <th align="left">Valor</th>
    </tr>
    <tr>
        <td>Transferencia</td>
        <td><strong>{{ $informe['codigo'] ?? '—' }}</strong> (id {{ (int) ($informe['transferencia_id'] ?? 0) }})</td>
    </tr>
    <tr>
        <td>Fecha</td>
        <td>{{ $informe['fecha'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Empresa</td>
        <td>{{ $informe['empresa_nombre'] ?? '—' }} ({{ (int) ($informe['empresa_id'] ?? 0) }})</td>
    </tr>
    <tr>
        <td>Tipo</td>
        <td>{{ $informe['tipo_abreviatura'] ?? '' }} {{ $informe['tipo_nombre'] ?? '' }}</td>
    </tr>
    <tr>
        <td>Depósito origen</td>
        <td>{{ $informe['deposito_origen'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Depósito destino</td>
        <td>{{ $informe['deposito_destino'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Centro costo destino</td>
        <td>{{ $informe['centrocosto_destino'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Usuario origen</td>
        <td>{{ $informe['usuario_origen'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Asiento</td>
        <td>
            {{ $informe['asiento_numero'] ?? '—' }}
            (id {{ (int) ($informe['asiento_id'] ?? 0) }})
        </td>
    </tr>
    <tr>
        <td>Total imputado TITO</td>
        <td><strong>{{ $fmt($informe['total_importe'] ?? 0) }}</strong></td>
    </tr>
</table>

<h3 style="margin:18px 0 6px 0;">Líneas TITO</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px; width:100%;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Ítem</th>
        <th align="left">SKU</th>
        <th align="left">Descripción</th>
        <th align="right">Cantidad</th>
        <th align="right">Precio promedio</th>
        <th align="right">Importe</th>
        <th align="left">Origen</th>
        <th align="left">Compras usadas</th>
    </tr>
    @foreach ($informe['lineas'] ?? [] as $fila)
        <tr>
            <td>{{ (int) ($fila['item'] ?? 0) }}</td>
            <td>{{ $fila['sku'] ?? '—' }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td align="right">{{ isset($fila['cantidad']) ? $fmt($fila['cantidad']) : '—' }}</td>
            <td align="right">
                @if (isset($fila['precio_promedio']) && $fila['precio_promedio'] !== null)
                    {{ $fmt6($fila['precio_promedio']) }}
                @else
                    —
                @endif
            </td>
            <td align="right">
                @if (isset($fila['importe']) && $fila['importe'] !== null)
                    {{ $fmt($fila['importe']) }}
                @else
                    —
                @endif
            </td>
            <td>{{ $fila['origen_etiqueta'] ?? '—' }}</td>
            <td>
                @if (! empty($fila['compras']))
                    <ul style="margin:0; padding-left:18px;">
                        @foreach ($fila['compras'] as $compra)
                            <li>
                                #{{ (int) ($compra['n'] ?? 0) }}:
                                {{ $fmt6($compra['precio'] ?? 0) }}
                                @if (! empty($compra['com']))
                                    · COM {{ (int) $compra['com'] }}
                                @endif
                                @if (! empty($compra['fecha']))
                                    · {{ $compra['fecha'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    —
                @endif
            </td>
        </tr>
    @endforeach
</table>

<p style="margin:18px 0 0 0; font-size:12px; color:#666;">
    El asiento recalcula el precio con
    <code>ArticuloPrecioPromedioCompraSupport</code>
    (3 COM ERP confirmadas, o fallback Anita stkm_pre_compra1/2/3).
</p>
</body>
</html>
