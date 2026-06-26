<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría Anita mensual</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $hayAlerta = (bool) ($informe['hay_alertas'] ?? false);
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría mensual solo Anita (venta / vengrav / ctamov / rendgastro)</h2>
<p style="margin:0 0 16px 0;">
    Jornada(s):
    <strong>{{ $informe['fecha_desde'] ?? '—' }}</strong>
    @if (($informe['fecha_hasta'] ?? '') !== ($informe['fecha_desde'] ?? ''))
        → <strong>{{ $informe['fecha_hasta'] ?? '—' }}</strong>
    @endif
</p>

@if ($hayAlerta)
    <p style="color:#dc3545; font-weight:bold;">Hay días con huecos correlativos en numeración Anita.</p>
@else
    <p style="color:#28a745; font-weight:bold;">Sin huecos correlativos Anita en el rango.</p>
@endif

<p style="margin:12px 0;">
    Incluye cabeceras venta Anita de gastronom&iacute;a/marketing (excluye FSL slots y FBI bingo). Sin conciliar anitaERP.
</p>
<p style="margin:12px 0 16px 0;">
    Adjunto Excel (.xlsx) con totales d&iacute;a a d&iacute;a por empresa.
</p>

@foreach ($informe['empresas'] ?? [] as $empresa)
    @php
        $totVenta = 0.0;
        $totVengrav = 0.0;
        $totCtamov = 0.0;
        $totRendg = 0.0;
        $diasAlerta = 0;
        foreach ($empresa['filas'] ?? [] as $fila) {
            if (($fila['estado'] ?? '') === '—') {
                continue;
            }
            $totVenta += (float) ($fila['total_venta_anita'] ?? 0);
            $totVengrav += (float) ($fila['total_vengrav_anita'] ?? 0);
            $totCtamov += (float) ($fila['total_ctamov_anita'] ?? 0);
            $totRendg += (float) ($fila['total_rendg_anita'] ?? 0);
            if (($fila['estado'] ?? '') === 'ALERTA') {
                $diasAlerta++;
            }
        }
    @endphp

    <h3 style="margin:18px 0 6px 0;">{{ $empresa['empresa_nombre'] ?? '' }} (id {{ $empresa['empresa_id'] ?? '' }})</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; margin-bottom:12px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Concepto</th>
            <th align="right">Total mes</th>
        </tr>
        <tr><td>venta Anita</td><td align="right">$ {{ $fmt($totVenta) }}</td></tr>
        <tr><td>vengrav Anita</td><td align="right">$ {{ $fmt($totVengrav) }}</td></tr>
        <tr><td>ctamov Anita</td><td align="right">$ {{ $fmt($totCtamov) }}</td></tr>
        <tr><td>rendgastro Anita (neto Z − NC)</td><td align="right">$ {{ $fmt($totRendg) }}</td></tr>
        <tr><td>Días con alerta correlatividad</td><td align="right">{{ $diasAlerta }}</td></tr>
    </table>
@endforeach

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · Modo {{ $informe['modo'] ?? 'solo_anita' }}
</p>
</body>
</html>
