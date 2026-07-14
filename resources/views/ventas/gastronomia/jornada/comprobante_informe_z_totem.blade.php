@php
    $d = $datos ?? [];
    $titulo = (string) ($d['titulo'] ?? 'Cierre de jornada');
    $subtitulo = (string) ($d['subtitulo'] ?? '');
    $empresa = (string) ($d['empresa_nombre'] ?? '');
    $fechaJornada = (string) ($d['fecha_jornada'] ?? '');
    $jornadaId = (int) ($d['jornada_id'] ?? 0);
    $emitido = (string) ($d['fecha_emision_comprobante'] ?? now()->format('d/m/Y H:i'));
    $apertura = (string) ($d['apertura_jornada_en'] ?? '');
    $cierre = (string) ($d['cierre_jornada_en'] ?? '');
    $rangoWaitry = (string) ($d['rango_waitry'] ?? '');
    $facturasCant = (int) ($d['facturas_cantidad'] ?? 0);
    $facturasTotal = (float) ($d['facturas_total'] ?? 0);
    $informeZ = is_array($d['informe_z'] ?? null) ? $d['informe_z'] : null;
    $informeZMeta = $informeZ;
    $conc = is_array($d['conciliacion'] ?? null) ? $d['conciliacion'] : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #222; margin: 12px 16px; }
        h1 { font-size: 15px; margin: 0 0 4px 0; }
        h2 { font-size: 11px; margin: 10px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 4px 6px; vertical-align: top; }
        th { background: #d4e6f1; font-weight: bold; text-align: left; }
        .cabecera-doc td { border: none !important; vertical-align: middle; }
        .cabecera-doc { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .logo { max-height: 50px; max-width: 180px; }
        .subtitulo { font-size: 9px; color: #444; }
        .muted { color: #555; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }
        .ok { background: #d4edda; padding: 6px 8px; margin: 6px 0 10px 0; border: 1px solid #b7dfc1; }
        .warn { background: #fff3cd; padding: 6px 8px; margin: 6px 0 10px 0; border: 1px solid #e2c36b; }
        .bloque { margin-bottom: 12px; page-break-inside: avoid; }
        .fila-discrepancia { background: #fff3cd; }
        .total-grande { font-weight: bold; background: #e8f4fc; }
    </style>
</head>
<body>
    <table class="cabecera-doc">
        <tr>
            <td style="width: 35%;">
                @if (!empty($d['logo']['uri']))
                    <img src="{{ $d['logo']['uri'] }}" alt="Logo" class="logo">
                @endif
            </td>
            <td style="width: 65%; text-align: right;">
                <h1>{{ $titulo }}</h1>
                @if ($subtitulo !== '')
                    <div class="subtitulo">{{ $subtitulo }}</div>
                @endif
                <div class="muted">Emitido: {{ $emitido }}</div>
            </td>
        </tr>
    </table>

    <h2>Resumen</h2>
    <table>
        <tbody>
            <tr>
                <th style="width: 25%;">Apertura</th>
                <td style="width: 25%;">{{ $apertura !== '' ? $apertura : '—' }}</td>
                <th style="width: 25%;">Cierre</th>
                <td style="width: 25%;">{{ $cierre !== '' ? $cierre : '—' }}</td>
            </tr>
            <tr>
                <th>Facturas (ERP)</th>
                <td class="num">{{ $facturasCant }}</td>
                <th>Total facturado (ERP)</th>
                <td class="num">$ {{ number_format($facturasTotal, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Rango Waitry</th>
                <td colspan="3">{{ $rangoWaitry !== '' ? $rangoWaitry : '—' }}</td>
            </tr>
        </tbody>
    </table>

    @if ($informeZ === null || empty($informeZ['totems']))
        <div class="warn">
            No hay Informe Z cargado para esta jornada.
        </div>
    @else
        @if (! empty($informeZMeta['precarga_automatica']))
            <p class="muted" style="margin:0 0 8px;">
                Informe Z precargado automáticamente al cerrar la jornada (Z = Sistema por medio de pago).
                @if (! empty($informeZMeta['ajustado_en_caja']))
                    Ajustado en caja{{ ! empty($informeZMeta['ajuste_caja_en']) ? ' el '.$informeZMeta['ajuste_caja_en'] : '' }}{{ ! empty($informeZMeta['ajuste_caja_usuario_nombre']) ? ' — '.$informeZMeta['ajuste_caja_usuario_nombre'] : '' }}.
                @endif
            </p>
        @endif

        @if ($conc !== null)
            @if (! empty($conc['ok']))
                <div class="ok">
                    Conciliación OK (tolerancia $ {{ number_format((float) ($conc['tolerancia'] ?? 0), 2, ',', '.') }}).
                </div>
            @else
                <div class="warn">
                    Hay diferencias (tolerancia $ {{ number_format((float) ($conc['tolerancia'] ?? 0), 2, ',', '.') }}).
                </div>
            @endif
        @endif

        @if ($informeZMeta && ! empty($informeZMeta['informe_z_en']))
            <p class="muted" style="margin:0 0 8px;">
                Registrado: {{ $informeZMeta['informe_z_en'] }}
                @if (! empty($informeZMeta['usuario_nombre']))
                    — {{ $informeZMeta['usuario_nombre'] }}
                @endif
            </p>
        @endif

        @foreach (($d['totems'] ?? []) as $t)
            @php
                $esUnificado = (int) ($t['totem_id'] ?? -1) === 0
                    || ! empty($t['plantilla_unificada']);
            @endphp
            <div class="bloque">
                <h2>
                    {{ $t['ubicacion_nombre'] ?? 'Informe Z Waitry' }}
                    @if (! $esUnificado && ! empty($t['detalle']))
                        — {{ $t['detalle'] }}
                    @endif
                    @if (! $esUnificado && ! empty($t['waitry_table_id']))
                        <span class="muted">(tableId {{ (int) $t['waitry_table_id'] }})</span>
                    @endif
                    @if (empty($t['ok']))
                        <span style="color:#922b21;"> — DIFERENCIA</span>
                    @endif
                </h2>

                <table>
                    <thead>
                        <tr class="total-grande">
                            <td colspan="3">
                                <strong>{{ $esUnificado ? 'Totales de la jornada' : 'Totales del tótem' }}</strong>
                            </td>
                            <td class="num">
                                Sist. $ {{ number_format((float) ($t['total_sistema'] ?? 0), 2, ',', '.') }}
                                / Z $ {{ number_format((float) ($t['total_informe_z'] ?? 0), 2, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <th style="width:40%;">Medio</th>
                            <th class="num" style="width:20%;">Sistema</th>
                            <th class="num" style="width:20%;">Informe Z</th>
                            <th class="num" style="width:20%;">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($t['lineas'] ?? []) as $ln)
                            <tr @if (empty($ln['ok'])) class="fila-discrepancia" @endif>
                                <td>{{ $ln['etiqueta'] ?? '—' }}</td>
                                <td class="num">{{ number_format((float) ($ln['monto_sistema'] ?? 0), 2, ',', '.') }}</td>
                                <td class="num">{{ number_format((float) ($ln['monto_informe_z'] ?? 0), 2, ',', '.') }}</td>
                                <td class="num">{{ number_format((float) ($ln['diferencia'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-grande">
                            <td><strong>Total</strong></td>
                            <td class="num"><strong>{{ number_format((float) ($t['total_sistema'] ?? 0), 2, ',', '.') }}</strong></td>
                            <td class="num"><strong>{{ number_format((float) ($t['total_informe_z'] ?? 0), 2, ',', '.') }}</strong></td>
                            <td class="num"><strong>{{ number_format((float) ($t['diferencia'] ?? 0), 2, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    @include('ventas.gastronomia.jornada.partials.cobros_post_cierre', ['d' => $d])
    @include('ventas.gastronomia.jornada.partials.transmision_faltante_z', ['d' => $d])
</body>
</html>

