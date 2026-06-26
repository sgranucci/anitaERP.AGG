<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría gastronomía {{ $informe['fecha_desde'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $hayDif = (bool) ($informe['hay_diferencias'] ?? false);
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría conciliación gastronomía (ERP / Anita / rendgastro)</h2>
<p style="margin:0 0 16px 0;">
    Jornada(s):
    <strong>{{ $informe['fecha_desde'] ?? '—' }}</strong>
    @if (($informe['fecha_hasta'] ?? '') !== ($informe['fecha_desde'] ?? ''))
        → <strong>{{ $informe['fecha_hasta'] ?? '—' }}</strong>
    @endif
    · Tolerancia $ {{ $fmt($informe['tolerancia'] ?? 0.02) }}
</p>

@if ($hayDif)
    <p style="color:#dc3545; font-weight:bold;">Hay diferencias fuera de tolerancia (estado DIF o SIN RENDG).</p>
@else
    <p style="color:#28a745; font-weight:bold;">Sin diferencias fuera de tolerancia en el rango.</p>
@endif

<p style="margin:16px 0 8px 0;">
    Adjunto Excel (y CSV) agrupado por <strong>PC</strong> (<code>identificador_pc</code> / <code>rendg_host</code>) y <strong>PV</strong> (CAE / CAEA):
    pv_cae, pv_caea, pc_total vs rendgastro Z, total_salon, post_cierre_caea, total_dia, control_gastro_total.
</p>

@foreach ($informe['empresas'] ?? [] as $empresa)
    @php $tieneFilas = false; @endphp
    @foreach ($empresa['dias'] ?? [] as $dia)
        @if (! empty($dia['filas']))
            @php $tieneFilas = true; @endphp
        @endif
    @endforeach
    @if (! $tieneFilas)
        @continue
    @endif

    <h3 style="margin:18px 0 6px 0;">{{ $empresa['empresa_nombre'] ?? '' }} (id {{ $empresa['empresa_id'] ?? '' }})</h3>

    @foreach ($empresa['dias'] ?? [] as $dia)
        @if (empty($dia['filas']))
            @continue
        @endif
        <p style="margin:12px 0 4px 0;"><strong>{{ $dia['fecha_jornada'] ?? '' }}</strong></p>

        <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:11px; margin-bottom:10px; width:100%;">
            <tr style="background:#85C1E9; color:#17202A;">
                <th>PC</th>
                <th>Tipo</th>
                <th>PV</th>
                <th align="right">ERP</th>
                <th align="right">Anita</th>
                <th align="right">Rendg Z</th>
                <th align="right">Δ ERP-Rendg</th>
                <th>Estado</th>
            </tr>
            @php $pcGrupo = null; @endphp
            @php
                $etiquetaTipo = static function (array $fila): string {
                    return match ($fila['tipo_fila'] ?? '') {
                        'pv_cae' => 'PV CAE',
                        'pv_caea' => 'PV CAEA',
                        'pc_total' => 'Total PC',
                        'total_salon' => 'TOTAL SALÓN',
                        'post_cierre_caea' => 'Post-cierre CAEA',
                        'vending_rendg' => 'Vending rendg',
                        'total_vending' => 'TOTAL VENDING',
                        'total_dia' => 'TOTAL DÍA',
                        'control_gastro_total' => 'Control día (neto)',
                        default => (string) ($fila['tipo_pv'] ?? $fila['tipo_fila'] ?? '—'),
                    };
                };
                $filasTabla = $dia['filas'] ?? [];
                if (! empty($dia['control_gastro_total']) && is_array($dia['control_gastro_total'])) {
                    $filasTabla[] = $dia['control_gastro_total'];
                }
            @endphp
            @foreach ($filasTabla as $fila)
                @php
                    $tipo = $fila['tipo_fila'] ?? '';
                    $bg = match ($tipo) {
                        'pc_total' => '#f9f9f9',
                        'total_salon' => '#eef6fb',
                        'post_cierre_caea' => '#fff8e6',
                        'vending_rendg' => '#f3e5f5',
                        'total_vending' => '#ede7f6',
                        'total_dia' => '#e8f5e9',
                        'control_gastro_total' => '#fdebd0',
                        default => '#fff',
                    };
                    $erpCol = $tipo === 'control_gastro_total'
                        ? ($fila['ventas_erp'] ?? 0)
                        : ($fila['ventas_erp'] ?? 0);
                    $rendgCol = $tipo === 'control_gastro_total'
                        ? ($fila['rendgastro_neto'] ?? null)
                        : ($fila['rendgastro_z'] ?? null);
                @endphp
                <tr style="background:{{ $bg }};">
                    <td>{{ $fila['identificador_pc'] ?? '' }}</td>
                    <td><strong>{{ $etiquetaTipo($fila) }}</strong></td>
                    <td>{{ $fila['pv_codigo'] ?? '—' }}</td>
                    <td align="right">{{ $fmt($erpCol) }}</td>
                    <td align="right">{{ $fmt($fila['ventas_anita'] ?? 0) }}</td>
                    <td align="right">{{ isset($rendgCol) ? $fmt($rendgCol) : '—' }}</td>
                    <td align="right">{{ isset($fila['diff_erp_rendg']) ? $fmt($fila['diff_erp_rendg']) : '—' }}</td>
                    <td>{{ $fila['estado'] ?? '—' }}</td>
                </tr>
            @endforeach
        </table>

        @php $ctrl = $dia['control_gastro_total'] ?? null; @endphp
        @if (is_array($ctrl) && (
            (($ctrl['rendg_legacy_z'] ?? null) !== null && (float) ($ctrl['rendg_legacy_z'] ?? 0) > 0.02)
            || (($ctrl['fc_caea_duplicado'] ?? null) !== null && (float) ($ctrl['fc_caea_duplicado'] ?? 0) > 0.02)
        ))
            <p style="margin:0 0 14px 0; font-size:12px; color:#c0392b;">
                @if (($ctrl['rendg_legacy_z'] ?? null) !== null && (float) ($ctrl['rendg_legacy_z'] ?? 0) > 0.02)
                    legacy Z $ {{ $fmt($ctrl['rendg_legacy_z']) }}
                @endif
                @if (($ctrl['fc_caea_duplicado'] ?? null) !== null && (float) ($ctrl['fc_caea_duplicado'] ?? 0) > 0.02)
                    · fc_caea dup $ {{ $fmt($ctrl['fc_caea_duplicado']) }}
                @endif
            </p>
        @endif
    @endforeach
@endforeach

<p style="margin-top:24px; font-size:12px; color:#666;">
    Comando:
    <code>php artisan gastronomia:conciliacion-diaria-reporte --fecha-desde={{ $informe['fecha_desde'] ?? '' }} --fecha-hasta={{ $informe['fecha_hasta'] ?? '' }} --enviar-mail</code>
</p>
</body>
</html>
