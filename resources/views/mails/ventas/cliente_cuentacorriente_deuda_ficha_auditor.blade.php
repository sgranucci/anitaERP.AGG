@php
    $alerta = ! empty($informe['requiere_alerta']);
    $empresa = trim((string) ($informe['empresa_nombre'] ?? ''));
    $fecha = (string) ($informe['fecha_fmt'] ?? $informe['fecha'] ?? '');
    $expresion = (string) ($informe['expresion'] ?? 'origen');
    $grupos = $informe['grupos'] ?? [];
    $resumen = $informe['resumen'] ?? [];
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
    <title>{{ $empresa }} — Deuda vs ficha clientes {{ $fecha }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px; margin:0; padding:16px;">
<div style="background:{{ $bannerBg }}; border:2px solid {{ $bannerBorder }}; color:{{ $bannerText }}; padding:14px 16px; border-radius:6px; margin-bottom:18px;">
    <div style="font-size:20px; font-weight:700; margin:0 0 4px 0;">{{ $empresa }}</div>
    <div style="font-size:15px; margin:0;">
        @if ($alerta)
            ALERTA — hay diferencias deuda vs ficha de clientes al {{ $fecha }}
        @else
            OK — todo cuadrado al {{ $fecha }}
        @endif
    </div>
</div>

<p style="margin:0 0 12px 0;">
    Control <strong>deuda vs ficha de cuenta corriente de clientes</strong>
    · Corte <strong>{{ $fecha }}</strong>
    · Expresión <strong>{{ $expresion }}</strong>
    · La deuda es la verdad; este mail solo informa (no graba ajustes).
</p>

<h3 style="margin:16px 0 6px 0;">Resumen</h3>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:13px;">
    <tr style="background:#85C1E9; color:#17202A;">
        <th align="left">Control</th>
        <th align="right">Valor</th>
    </tr>
    <tr>
        <td>Movimientos leídos</td>
        <td align="right">{{ (int) ($resumen['movimientos'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Clientes / monedas con diferencia</td>
        <td align="right">{{ (int) ($informe['grupos_total'] ?? count($grupos)) }}</td>
    </tr>
    @if ($expresion === 'origen')
        <tr>
            <td>Suma diferencia origen (deuda − ficha)</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_origen'] ?? 0) }}</td>
        </tr>
    @else
        <tr>
            <td>Suma diferencia pesos</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_pesos'] ?? 0) }}</td>
        </tr>
        <tr>
            <td>Suma diferencia dólares</td>
            <td align="right">{{ $fmt($resumen['suma_diferencia_dolares'] ?? 0) }}</td>
        </tr>
    @endif
</table>

@if ($alerta)
    <h3 style="margin:18px 0 6px 0;">Diferencias cliente por cliente</h3>
    <p style="margin:0 0 8px 0; color:#555; font-size:13px;">
        Revisar motivos posibles: aplicaciones posteriores al corte, importes multi-moneda,
        renglones de ficha sin deuda abierta, o ajustes previos.
    </p>
    <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; width:100%;">
        <tr style="background:#f5b7b1; color:#17202A;">
            <th align="left">Código</th>
            <th align="left">Cliente</th>
            <th align="left">Moneda</th>
            @if ($expresion === 'origen')
                <th align="right">Deuda</th>
                <th align="right">Ficha</th>
                <th align="right">Diferencia</th>
            @else
                <th align="right">Deuda $</th>
                <th align="right">Ficha $</th>
                <th align="right">Dif. $</th>
                <th align="right">Dif. USD</th>
            @endif
            <th align="right">Ajuste propuesto</th>
        </tr>
        @foreach ($grupos as $g)
            <tr>
                <td>{{ $g['cliente_codigo'] ?? '' }}</td>
                <td>{{ $g['cliente_nombre'] ?? '' }}</td>
                <td>{{ $g['moneda_abreviatura'] ?? '' }}</td>
                @if ($expresion === 'origen')
                    <td align="right">{{ $fmt($g['deuda_origen'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($g['ficha_origen'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($g['diferencia_origen'] ?? 0) }}</strong></td>
                @else
                    <td align="right">{{ $fmt($g['deuda_pesos'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($g['ficha_pesos'] ?? 0) }}</td>
                    <td align="right"><strong>{{ $fmt($g['diferencia_pesos'] ?? 0) }}</strong></td>
                    <td align="right">{{ $fmt($g['diferencia_usd'] ?? 0) }}</td>
                @endif
                <td align="right">{{ $fmt($g['ajuste_propuesto'] ?? 0) }}</td>
            </tr>
        @endforeach
    </table>
    @if ((int) ($informe['grupos_truncados'] ?? 0) > 0)
        <p style="margin:8px 0 0 0; color:#666; font-size:12px;">
            Se omitieron {{ (int) $informe['grupos_truncados'] }} fila(s) por límite de mail.
            Correr
            <code>php artisan ventas:cuadrar-deuda-ficha-cliente --fecha={{ $informe['fecha'] ?? '' }} --expresion={{ $expresion }} --empresa={{ (int) ($informe['empresa_id'] ?? 0) }}</code>
            para el detalle completo.
        </p>
    @endif
@else
    <p style="margin:18px 0 0 0; padding:12px; background:#d5f5e3; border:1px solid #1e8449; color:#145a32; border-radius:4px;">
        No hay diferencias entre la deuda abierta y la ficha de cuenta corriente de clientes para
        <strong>{{ $empresa }}</strong> al corte <strong>{{ $fecha }}</strong>.
    </p>
@endif

<p style="margin-top:22px; color:#666; font-size:12px;">
    Generado {{ now()->format('d/m/Y H:i') }} —
    <code>php artisan ventas:auditar-deuda-ficha-cliente</code>
    · Destinatario: <code>VENTAS_DEUDA_FICHA_AUDITOR_EMAIL</code>
    · Desactivar: <code>VENTAS_DEUDA_FICHA_AUDITOR_HABILITADA=false</code>
</p>
</body>
</html>
