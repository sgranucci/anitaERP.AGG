<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría rendgastro {{ $informe['fecha_jornada'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.');
    $fmtDiff = static function ($n) use ($fmt) {
        if ($n === null || $n === '') {
            return '—';
        }
        $v = (float) $n;

        return ($v > 0 ? '+' : '').$fmt($v);
    };
    $requiereAlerta = (bool) ($informe['requiere_alerta'] ?? false);
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría rendgastro vs ERP (gastronomía + estacionamiento)</h2>
<p style="margin:0 0 16px 0;">
    Fecha jornada: <strong>{{ $informe['fecha_jornada'] ?? '—' }}</strong>
    · Tolerancia $ {{ $fmt($informe['tolerancia'] ?? 0.02) }}
</p>

@if ($requiereAlerta)
    <p style="color:#dc3545; font-weight:bold;">
        Hay desvíos fuera de tolerancia entre ERP y rendgastro (Δ rendg).
    </p>
@else
    <p style="color:#28a745; font-weight:bold;">Consistente en todas las empresas auditadas.</p>
@endif

@foreach ($informe['empresas'] ?? [] as $bloque)
    @php
        $detalle = $bloque['informe'] ?? [];
        $resumen = $detalle['resumen'] ?? [];
        $conteo = $resumen['conteo'] ?? [];
        $totalDia = $detalle['total_dia'] ?? null;
    @endphp
    <h3 style="margin:18px 0 6px 0;">
        {{ $bloque['empresa_nombre'] ?? ('Empresa '.($bloque['empresa_id'] ?? '')) }}
        (id {{ $bloque['empresa_id'] ?? '—' }})
    </h3>
    <p style="margin:0 0 8px 0;">
        OK {{ (int) ($conteo['ok'] ?? 0) }}
        · DIF rendg {{ (int) ($conteo['dif_rendg'] ?? 0) }}
        · Sin rendg {{ (int) ($conteo['sin_rendg'] ?? 0) }}
    </p>

    @if ($totalDia !== null)
        <p style="margin:0 0 8px 0;">
            Total día: ERP $ {{ $fmt($totalDia['erp_z'] ?? null) }}
            · rendg $ {{ $fmt($totalDia['anita_z'] ?? null) }}
            · Δ rendg $ {{ $fmtDiff($totalDia['diff_z'] ?? null) }}
            ({{ $totalDia['estado_rendg'] ?? '—' }})
            · <strong>{{ $totalDia['estado'] ?? '—' }}</strong>
        </p>
    @endif

    @if (! empty($detalle['filas']))
        <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:11px; width:100%; margin-bottom:16px;">
            <tr style="background:#85C1E9; color:#17202A;">
                <th align="left">Tipo</th>
                <th align="left">Clave</th>
                <th>Estado</th>
                <th>Rendg</th>
                <th align="right">Fac</th>
                <th align="right">ERP total</th>
                <th align="right">Rendg Z</th>
                <th align="right">Δ rendg</th>
            </tr>
            @foreach ($detalle['filas'] as $fila)
                <tr>
                    <td>{{ $fila['tipo_fila'] ?? '—' }}</td>
                    <td>{{ $fila['puntoventa'] ?? '—' }}</td>
                    <td>{{ $fila['estado'] ?? '—' }}</td>
                    <td>{{ $fila['estado_rendg'] ?? '—' }}</td>
                    <td align="right">{{ (int) ($fila['cantidad_facturas_erp'] ?? 0) }}</td>
                    <td align="right">{{ $fmt($fila['erp_z'] ?? null) }}</td>
                    <td align="right">{{ $fmt($fila['anita_z'] ?? null) }}</td>
                    <td align="right">{{ $fmtDiff($fila['diff_z'] ?? null) }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p style="color:#666;">Sin actividad en esta fecha.</p>
    @endif
@endforeach

<p style="margin-top:24px; font-size:12px; color:#666;">
    <strong>Δ rendg</strong>: ERP vs <code>rendg_total_z</code> en rendgastro por PC (salón) o por PV (estacionamiento).<br>
    Filas <strong>ESTAC …</strong>: circuito estacionamiento por PV.<br>
    Comando manual:
    <code>php artisan rendicion-gastronomia:auditoria-anita --fecha={{ $informe['fecha_jornada'] ?? '' }}</code>
</p>
</body>
</html>
