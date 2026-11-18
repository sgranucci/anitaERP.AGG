@php
    $alerta = ! empty($informe['requiere_alerta']);
    $empresa = trim((string) ($informe['empresa_nombre'] ?? ''));
    $fecha = (string) ($informe['fecha_fmt'] ?? $informe['fecha'] ?? '');
    $desde = (string) ($informe['desde_fmt'] ?? $informe['desde'] ?? '');
    $hasta = (string) ($informe['hasta_fmt'] ?? $informe['hasta'] ?? '');
    $duplicados = $informe['duplicados'] ?? [];
    $bannerBg = $alerta ? '#fadbd8' : '#d5f5e3';
    $bannerBorder = $alerta ? '#c0392b' : '#1e8449';
    $bannerText = $alerta ? '#7b241c' : '#145a32';
    $fmt = static function ($n) {
        return number_format((float) $n, 2, ',', '.');
    };
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $empresa }} — Duplicados subdiario {{ $fecha }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px; margin:0; padding:16px;">
<div style="background:{{ $bannerBg }}; border:2px solid {{ $bannerBorder }}; color:{{ $bannerText }}; padding:14px 16px; border-radius:6px; margin-bottom:18px;">
    <div style="font-size:20px; font-weight:700; margin:0 0 4px 0;">{{ $empresa }}</div>
    <div style="font-size:15px; margin:0;">
        @if ($alerta)
            ALERTA — hay asientos de Anita (subdiario) que repiten facturas ya transferidas
        @else
            OK — no hay asientos subdiario duplicados contra transferencia/venta
        @endif
    </div>
</div>

<p style="margin:0 0 12px 0;">
    Control diario Crown: asientos <code>subdiario</code>/<code>subhist</code> cuyo comprobante
    (tipo|letra|PV|número) ya tiene asiento de <strong>transferencia</strong> o <strong>venta</strong>.
    Ventana <strong>{{ $desde }} → {{ $hasta }}</strong>
    ({{ (int) ($informe['ventana_dias'] ?? 0) }} días).
    El import Anita debería omitirlos; si aparecen, el mayor queda al doble.
</p>

<h3 style="margin:16px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Concepto</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Asientos duplicados</td>
        <td align="right">{{ (int) ($informe['cantidad'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Importe (Debe de los asientos Anita)</td>
        <td align="right">{{ $fmt($informe['importe_total'] ?? 0) }}</td>
    </tr>
</table>

@if ($alerta)
    <h3 style="margin:18px 0 6px 0;">Muestra</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Fecha</th>
            <th align="left">Documento</th>
            <th align="right">Id subd</th>
            <th align="right">Id transf</th>
            <th align="right">venta_id</th>
            <th align="right">Importe</th>
        </tr>
        @foreach ($duplicados as $fila)
            <tr>
                <td>{{ \Carbon\Carbon::parse((string) ($fila['fecha'] ?? ''))->format('d/m/Y') }}</td>
                <td>{{ $fila['clave'] ?? '' }}</td>
                <td align="right">{{ (int) ($fila['asiento_id'] ?? 0) }}</td>
                <td align="right">{{ (int) ($fila['proceso_id'] ?? 0) }}</td>
                <td align="right">{{ (int) ($fila['venta_id'] ?? 0) ?: '' }}</td>
                <td align="right">{{ $fmt($fila['importe'] ?? 0) }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['duplicados_omitidos'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#666;">
            … y {{ (int) $informe['duplicados_omitidos'] }} más (límite del mail).
        </p>
    @endif

    <h3 style="margin:18px 0 6px 0;">Limpieza</h3>
    <p style="margin:0 0 8px 0;">
        Dry-run y, con autorización, borrar los asientos Anita duplicados (no toca ventas ni transferencia):
    </p>
    <pre style="background:#f4f6f7; padding:10px; border-radius:4px; font-size:12px; white-space:pre-wrap;">{{ $informe['comando_limpieza'] ?? '' }}

# Persistencia (mismo rango):
{{ str_replace('--dry-run', '--ejecutar', (string) ($informe['comando_limpieza'] ?? '')) }}</pre>
@endif

<p style="margin:20px 0 0 0; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} ·
    <code>contable:auditar-asientos-subdiario-duplicados-transferencia</code>
</p>
</body>
</html>
