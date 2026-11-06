@php
    $alerta = ! empty($informe['requiere_alerta']);
    $empresa = trim((string) ($informe['empresa_nombre'] ?? ''));
    $fecha = (string) ($informe['fecha_fmt'] ?? $informe['fecha'] ?? '');
    $desvios = $informe['desvios'] ?? [];
    $pendientes = $informe['pendientes'] ?? [];
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
    <title>{{ $empresa }} — Factura anticipada vs recepción {{ $fecha }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px; margin:0; padding:16px;">
<div style="background:{{ $bannerBg }}; border:2px solid {{ $bannerBorder }}; color:{{ $bannerText }}; padding:14px 16px; border-radius:6px; margin-bottom:18px;">
    <div style="font-size:20px; font-weight:700; margin:0 0 4px 0;">{{ $empresa }}</div>
    <div style="font-size:15px; margin:0;">
        @if ($alerta)
            ALERTA — hay OC con recepción en anticipos mayor que la factura anticipada al {{ $fecha }}
        @else
            OK — ninguna recepción supera la factura anticipada al {{ $fecha }}
        @endif
    </div>
</div>

<p style="margin:0 0 12px 0;">
    Control <strong>factura anticipada vs recepción</strong> en la cuenta de anticipos.
    Por cada OC se suma el Debe de facturas anticipadas y el Haber de recepciones.
    Si la recepción acredita más de lo facturado, es desvío.
    El pendiente (factura sin recepción completa) se informa y no alerta.
    Las OPA no entran: las mira el auditor de deuda vs ficha / delta conjunto.
</p>

<h3 style="margin:16px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Control</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Órdenes con movimiento en anticipos</td>
        <td align="right">{{ (int) ($informe['ordenes_total'] ?? 0) }}</td>
    </tr>
    <tr
        @if ($alerta)
            style="background:#fadbd8;"
        @endif
    >
        <td>Desvíos (recepción &gt; factura)</td>
        <td align="right"><strong>{{ (int) ($informe['desvios_total'] ?? 0) }}</strong></td>
    </tr>
    <tr>
        <td>Suma exceso recepción</td>
        <td align="right">{{ $fmt($informe['exceso_recepcion'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Pendientes de recepción (info)</td>
        <td align="right">{{ (int) ($informe['pendientes_total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Tolerancia</td>
        <td align="right">{{ $fmt($informe['tolerancia'] ?? 1) }}</td>
    </tr>
</table>

@if ($desvios !== [])
    <h3 style="margin:18px 0 6px 0;">Desvíos</h3>
    <p style="margin:0 0 8px 0; color:#7b241c; font-size:13px;">
        La recepción acreditó anticipos por encima de lo facturado anticipado en esa OC.
        Es el caso que antes ensuciaba el delta conjunto (tope de recepción).
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f5b7b1; color:#17202A;">
            <th align="left">OC</th>
            <th align="left">Cuenta</th>
            <th align="right">Factura anticipada</th>
            <th align="right">Recepción</th>
            <th align="right">Neto</th>
            <th align="right">Exceso</th>
        </tr>
        @foreach ($desvios as $fila)
            <tr>
                <td>{{ $fila['numeroordencompra'] ?? $fila['ordencompra_id'] ?? '' }}</td>
                <td>{{ $fila['cuenta_codigo'] ?? '' }} {{ $fila['cuenta_nombre'] ?? '' }}</td>
                <td align="right">{{ $fmt($fila['factura'] ?? 0) }}</td>
                <td align="right">{{ $fmt($fila['recepcion'] ?? 0) }}</td>
                <td align="right">{{ $fmt($fila['neto'] ?? 0) }}</td>
                <td align="right"><strong>{{ $fmt($fila['exceso_recepcion'] ?? 0) }}</strong></td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['desvios_truncados'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#666; font-size:12px;">
            Se omitieron {{ (int) $informe['desvios_truncados'] }} fila(s) por límite de mail.
        </p>
    @endif
@endif

@if ($pendientes !== [])
    <h3 style="margin:18px 0 6px 0;">Pendientes de recepción (sin alerta)</h3>
    <p style="margin:0 0 8px 0; color:#555; font-size:13px;">
        Queda Debe de factura anticipada sin Haber de recepción (o recepción parcial). Es el circuito normal.
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">OC</th>
            <th align="right">Factura</th>
            <th align="right">Recepción</th>
            <th align="right">Pendiente</th>
        </tr>
        @foreach ($pendientes as $fila)
            <tr>
                <td>{{ $fila['numeroordencompra'] ?? $fila['ordencompra_id'] ?? '' }}</td>
                <td align="right">{{ $fmt($fila['factura'] ?? 0) }}</td>
                <td align="right">{{ $fmt($fila['recepcion'] ?? 0) }}</td>
                <td align="right">{{ $fmt($fila['pendiente_recepcion'] ?? 0) }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['pendientes_truncados'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#666; font-size:12px;">
            Se omitieron {{ (int) $informe['pendientes_truncados'] }} pendiente(s) por límite de mail.
        </p>
    @endif
@endif

@if (! $alerta && $desvios === [])
    <p style="margin:18px 0 0 0; padding:12px; background:#d5f5e3; border:1px solid #1e8449; color:#145a32; border-radius:4px;">
        Ninguna OC tiene recepción en anticipos mayor que su factura anticipada.
    </p>
@endif

<p style="margin-top:22px; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} —
    <code>php artisan compras:auditar-factura-anticipada-recepcion</code>
    · Destinatario: <code>COMPRAS_FACTURA_ANTICIPADA_RECEPCION_AUDITOR_EMAIL</code>
    · Empresas: <code>COMPRAS_FACTURA_ANTICIPADA_RECEPCION_AUDITOR_EMPRESAS</code>
    · Desactivar: <code>COMPRAS_FACTURA_ANTICIPADA_RECEPCION_AUDITOR_HABILITADA=false</code>
</p>
</body>
</html>
