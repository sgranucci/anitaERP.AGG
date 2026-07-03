<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuadre jornada</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $hayDif = (bool) ($informe['hay_diferencias'] ?? false);
@endphp

<h2 style="margin:0 0 8px 0;">Cuadre jornada: contabilidad vs rendiciones vs ventas</h2>
<p style="margin:0 0 16px 0;">
    Jornada(s):
    <strong>{{ $informe['fecha_desde'] ?? '—' }}</strong>
    @if (($informe['fecha_hasta'] ?? '') !== ($informe['fecha_desde'] ?? ''))
        → <strong>{{ $informe['fecha_hasta'] ?? '—' }}</strong>
    @endif
</p>

@if ($hayDif)
    <p style="color:#dc3545; font-weight:bold;">Hay d&iacute;as donde no coinciden los cinco totales.</p>
@else
    <p style="color:#28a745; font-weight:bold;">Todos los d&iacute;as del rango cuadran dentro de la tolerancia.</p>
@endif

<p style="margin:12px 0 16px 0;">
    Columnas del CSV adjunto: contabilidad (ctamov), rendiciones (Z − NC), venta Informix, venta ERP, flash (AyB + estacionamiento).
</p>

@foreach ($informe['empresas'] ?? [] as $empresa)
    @php
        $diasDif = 0;
        $totCont = 0.0;
        foreach ($empresa['filas'] ?? [] as $fila) {
            if (($fila['estado'] ?? '') === 'DIF') {
                $diasDif++;
            }
            if (($fila['estado'] ?? '') !== '—') {
                $totCont += (float) ($fila['total_contabilidad'] ?? 0);
            }
        }
    @endphp

    <h3 style="margin:18px 0 6px 0;">{{ $empresa['empresa_nombre'] ?? '' }} (id {{ $empresa['empresa_id'] ?? '' }})</h3>
    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; font-size:12px; margin-bottom:12px;">
        <tr style="background:#85C1E9; color:#17202A;">
            <th align="left">Concepto</th>
            <th align="right">Valor</th>
        </tr>
        <tr><td>Total contabilidad (suma d&iacute;as activos)</td><td align="right">$ {{ $fmt($totCont) }}</td></tr>
        <tr><td>D&iacute;as con diferencia</td><td align="right">{{ $diasDif }}</td></tr>
    </table>
@endforeach

<p style="margin-top:20px; font-size:12px; color:#666;">
    Generado {{ now()->format('d/m/Y H:i') }} · Tolerancia $ {{ number_format((float) ($informe['tolerancia'] ?? 0), 2, ',', '.') }}
</p>
</body>
</html>
