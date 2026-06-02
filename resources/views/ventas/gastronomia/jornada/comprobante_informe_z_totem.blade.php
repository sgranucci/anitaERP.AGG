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

        @foreach (($d['totems'] ?? []) as $t)
            <div class="bloque">
                <h2>
                    {{ $t['ubicacion_nombre'] ?? 'Tótem' }}
                    @if (! empty($t['detalle']))
                        — {{ $t['detalle'] }}
                    @endif
                    @if (! empty($t['waitry_table_id']))
                        <span class="muted">(tableId {{ (int) $t['waitry_table_id'] }})</span>
                    @endif
                    @if (! empty($t['diferencia']) && abs((float) $t['diferencia']) > 0.0001)
                        <span class="muted">— diferencia $ {{ number_format((float) $t['diferencia'], 2, ',', '.') }}</span>
                    @endif
                </h2>

                <table>
                    <thead>
                        <tr>
                            <th style="width:55%;">Medio</th>
                            <th class="num" style="width:45%;">Monto Informe Z</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($t['lineas'] ?? []) as $ln)
                            <tr>
                                <td>{{ $ln['etiqueta'] ?? '—' }}</td>
                                <td class="num">{{ number_format((float) ($ln['monto'] ?? 0), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Total</strong></td>
                            <td class="num"><strong>{{ number_format((float) ($t['total'] ?? 0), 2, ',', '.') }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
</body>
</html>

