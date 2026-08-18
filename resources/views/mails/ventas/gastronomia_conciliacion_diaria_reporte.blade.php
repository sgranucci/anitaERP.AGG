<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría conciliación {{ $informe['fecha_desde'] ?? '' }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#222; font-size:14px;">
@php
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $hayDif = (bool) ($informe['hay_diferencias'] ?? false);
    $etiquetaTipo = static function (array $fila): string {
        return match ($fila['tipo_fila'] ?? '') {
            'pv_cae' => 'PV CAE',
            'pv_caea' => 'PV CAEA',
            'pc_total' => 'Total PC',
            'total_salon' => 'TOTAL SALÓN',
            'total_gastro' => 'TOTAL GASTRO',
            'post_cierre_caea' => 'Post-cierre CAEA',
            'caea_agregados_migrados' => 'Agregados CAEA',
            'estacionamiento_pv' => 'PV estacionamiento',
            'total_estacionamiento' => 'TOTAL ESTAC.',
            'vending_pv' => 'PV vending',
            'total_vending' => 'TOTAL VENDING',
            'control_gastro_total' => 'Control día (neto)',
            'control_flash' => 'Control flash (caja)',
            'control_flash_gastro' => 'Flash gastro (AyB+vending Anita vs ERP/Rendg)',
            'control_flash_estacionamiento' => 'Flash estac. (vs rendg)',
            default => (string) ($fila['tipo_pv'] ?? $fila['tipo_fila'] ?? '—'),
        };
    };
@endphp

<h2 style="margin:0 0 8px 0;">Auditoría conciliación (ERP / Anita / rendgastro)</h2>
<p style="margin:0 0 16px 0;">
    Jornada(s):
    <strong>{{ $informe['fecha_desde'] ?? '—' }}</strong>
    @if (($informe['fecha_hasta'] ?? '') !== ($informe['fecha_desde'] ?? ''))
        → <strong>{{ $informe['fecha_hasta'] ?? '—' }}</strong>
    @endif
    · Tolerancia $ {{ $fmt($informe['tolerancia'] ?? 0.02) }}
</p>

@if ($hayDif)
    <p style="color:#dc3545; font-weight:bold;">Hay diferencias fuera de tolerancia en algún circuito (GASTRO / ESTACIONAMIENTO / VENDING).</p>
@else
    <p style="color:#28a745; font-weight:bold;">Sin diferencias fuera de tolerancia en el rango.</p>
@endif

@php $hayHuecos = (bool) ($informe['hay_huecos_numeracion'] ?? false); @endphp
@if ($hayHuecos)
    <p style="color:#dc3545; font-weight:bold;">Hay huecos en numeración de comprobantes (ERP y/o Anita) en el rango.</p>
@else
    <p style="color:#28a745; font-weight:bold;">Sin huecos en numeración en el rango.</p>
@endif

@php
    $cantPendArca = 0;
    foreach ($informe['empresas'] ?? [] as $empPend) {
        foreach ($empPend['dias'] ?? [] as $diaPend) {
            $cantPendArca += (int) (($diaPend['huecos_arca_pendientes']['cantidad'] ?? 0));
        }
    }
@endphp
@if ($cantPendArca > 0)
    <p style="color:#dc3545; font-weight:bold;">
        Hay {{ $cantPendArca }} hueco(s) ARCA pendiente(s) de saneamiento (cierre con ARCA caído o lote no ejecutado).
        Comando: <code>php artisan gastronomia:sanear-huecos-arca --empresa=… --fecha-jornada=… --dry-run</code>
    </p>
@endif

<p style="margin:16px 0 8px 0;">
    Adjunto Excel (y CSV) por circuito: <strong>GASTRO</strong> (sal&oacute;n), <strong>ESTACIONAMIENTO</strong> (PV ERP vs rendgastro), <strong>VENDING</strong> (rendiciones ERP vs rendgastro), <strong>FLASH</strong> (caja Informix: flash_ayb incluye vending mientras Anita no lo discrimine; flash_estac vs rendgastro; jornada anterior a la auditada).
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

        @php
            $filasTabla = $dia['filas'] ?? [];
            if (! empty($dia['control_gastro_total']) && is_array($dia['control_gastro_total'])) {
                $filasTabla[] = $dia['control_gastro_total'];
            }
            if (! empty($dia['control_flash']) && is_array($dia['control_flash'])) {
                if (array_is_list($dia['control_flash'])) {
                    foreach ($dia['control_flash'] as $filaFlash) {
                        if (is_array($filaFlash)) {
                            $filasTabla[] = $filaFlash;
                        }
                    }
                } else {
                    $filasTabla[] = $dia['control_flash'];
                }
            }
            $porCircuito = [];
            foreach ($filasTabla as $f) {
                $c = (string) ($f['circuito'] ?? 'GASTRO');
                $porCircuito[$c][] = $f;
            }
        @endphp

        @foreach (['GASTRO', 'ESTACIONAMIENTO', 'VENDING', 'FLASH'] as $circuito)
            @if (empty($porCircuito[$circuito]))
                @continue
            @endif
            <p style="margin:14px 0 6px 0; font-weight:bold; color:#1a5276;">Circuito {{ $circuito }}</p>
            @php $tieneFlashControl = $circuito === 'FLASH'; @endphp
            <table cellpadding="5" cellspacing="0" border="1" style="border-collapse:collapse; font-size:11px; margin-bottom:10px; width:100%;">
                <tr style="background:#85C1E9; color:#17202A;">
                    <th>Clave</th>
                    <th>Tipo</th>
                    <th>PV</th>
                    <th align="right">ERP</th>
                    @if ($tieneFlashControl)
                        <th align="right">Rendg</th>
                        <th align="right">Flash</th>
                        <th align="right">Δ Rendg-Flash</th>
                    @else
                        <th align="right">Anita</th>
                        <th align="right">Rendg</th>
                        <th align="right">Δ ERP-Rendg</th>
                    @endif
                    <th>Estado</th>
                </tr>
                @foreach ($porCircuito[$circuito] as $fila)
                    @php
                        $tipo = $fila['tipo_fila'] ?? '';
                        $erpCol = (float) ($fila['ventas_erp'] ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $fila['identificador_pc'] ?? '' }}</td>
                        <td><strong>{{ $etiquetaTipo($fila) }}</strong></td>
                        <td>{{ $fila['pv_codigo'] ?? '—' }}</td>
                        <td align="right">{{ $fmt($erpCol) }}</td>
                        @if ($tieneFlashControl)
                            @php $rendgFlash = $fila['rendgastro_neto'] ?? $fila['rendgastro_z'] ?? null; @endphp
                            <td align="right">{{ $rendgFlash !== null ? $fmt($rendgFlash) : '—' }}</td>
                            <td align="right">{{ $fmt($fila['total_flash'] ?? 0) }}</td>
                            <td align="right">{{ isset($fila['diff_rendg_flash']) ? $fmt($fila['diff_rendg_flash']) : '—' }}</td>
                        @else
                            <td align="right">
                                {{ ! empty($fila['ventas_solo_erp']) || ! array_key_exists('ventas_anita', $fila) || $fila['ventas_anita'] === null
                                    ? '—'
                                    : $fmt($fila['ventas_anita']) }}
                            </td>
                            @php $rendgCol = $fila['rendgastro_neto'] ?? $fila['rendgastro_z'] ?? null; @endphp
                            <td align="right">{{ $rendgCol !== null ? $fmt($rendgCol) : '—' }}</td>
                            <td align="right">{{ isset($fila['diff_erp_rendg']) ? $fmt($fila['diff_erp_rendg']) : '—' }}</td>
                        @endif
                        <td>{{ $fila['estado'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        @endforeach

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
