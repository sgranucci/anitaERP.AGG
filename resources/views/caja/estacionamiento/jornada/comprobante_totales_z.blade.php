@php
    $d = $datos ?? [];
    $titulo = (string) ($d['titulo'] ?? 'Totales Z');
    $subtitulo = (string) ($d['subtitulo'] ?? '');
    $emitido = (string) ($d['fecha_emision_comprobante'] ?? now()->format('d/m/Y H:i'));
    $filasZ = is_array($d['filas_z'] ?? null) ? $d['filas_z'] : [];
    $numeracionFilas = is_array($d['numeracion_filas'] ?? null) ? $d['numeracion_filas'] : [];
    $totales = is_array($d['totales_jornada'] ?? null) ? $d['totales_jornada'] : [];
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
        .fila-discrepancia { background: #fff3cd; }
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
                <div class="subtitulo">{{ $subtitulo }}</div>
                <div class="muted">Emitido: {{ $emitido }}</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Fecha jornada</th>
            <td>{{ $d['fecha_jornada'] ?? '—' }}</td>
            <th>Apertura</th>
            <td>{{ $d['apertura_jornada_en'] ?? '—' }}</td>
            <th>Cierre</th>
            <td>{{ $d['cierre_jornada_en'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Usuario apertura</th>
            <td>{{ $d['usuario_apertura'] ?? '—' }}</td>
            <th>Usuario cierre</th>
            <td colspan="3">{{ $d['usuario_cierre'] ?? '—' }}</td>
        </tr>
    </table>

    <h2>Totales del día (ERP)</h2>
    <table>
        <tr>
            <th class="num">Facturación bruta</th>
            <th class="num">Cobrado</th>
            <th class="num">NC</th>
            <th class="num">Invitaciones</th>
        </tr>
        <tr>
            <td class="num">$ {{ number_format((float) ($totales['total_ventas'] ?? 0), 2, ',', '.') }}</td>
            <td class="num">$ {{ number_format((float) ($totales['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
            <td class="num">$ {{ number_format(abs((float) ($totales['total_notas_credito'] ?? 0)), 2, ',', '.') }}</td>
            <td class="num">$ {{ number_format((float) ($totales['total_invitaciones'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </table>

    @if (! empty($d['numeracion_resumen']))
        <h2>Numeración comprobantes</h2>
        <p class="muted">{{ $d['numeracion_resumen'] }}</p>
        @if ($numeracionFilas !== [])
            <table>
                <thead>
                    <tr>
                        <th>Punto venta</th>
                        <th>Último ticket</th>
                        <th>Última NC</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($numeracionFilas as $fila)
                    <tr>
                        <td>{{ $fila['puntoventa_etiqueta'] ?? $fila['puntoventa_codigo'] ?? '—' }}</td>
                        <td class="num">{{ $fila['ultimo_ticket'] ?? '—' }}</td>
                        <td class="num">{{ $fila['ultima_nc'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    <h2>Conciliación Totales Z por punto de venta</h2>
    @if (! ($d['auditoria_disponible'] ?? false))
        <div class="warn">Auditoría Anita no disponible. Revise sincronización o conectividad.</div>
    @elseif ($d['auditoria_ok'] ?? false)
        <div class="ok">Totales Z cuadran entre ERP y Anita (tolerancia $ {{ number_format((float) ($d['tolerancia'] ?? 0), 2, ',', '.') }}).</div>
    @else
        <div class="warn">Hay diferencias entre ERP y Anita (tolerancia $ {{ number_format((float) ($d['tolerancia'] ?? 0), 2, ',', '.') }}).</div>
    @endif

    @if ($filasZ !== [])
        <table>
            <thead>
                <tr>
                    <th>PV</th>
                    <th>Estado</th>
                    <th class="num">Z ERP</th>
                    <th class="num">Z Anita</th>
                    <th class="num">Dif. Z</th>
                    <th class="num">NC ERP</th>
                    <th class="num">NC Anita</th>
                    <th class="num">Dif. NC</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filasZ as $fila)
                @php $diff = ($fila['estado'] ?? '') !== 'ok'; @endphp
                <tr @if ($diff) class="fila-discrepancia" @endif>
                    <td>{{ $fila['puntoventa'] ?? '—' }}</td>
                    <td>{{ $fila['estado'] ?? '—' }}</td>
                    <td class="num">$ {{ number_format((float) ($fila['erp_z'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">
                        @if ($fila['anita_z'] !== null)
                            $ {{ number_format((float) $fila['anita_z'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">
                        @if ($fila['diff_z'] !== null)
                            $ {{ number_format((float) $fila['diff_z'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">$ {{ number_format((float) ($fila['erp_nc'] ?? 0), 2, ',', '.') }}</td>
                    <td class="num">
                        @if ($fila['anita_nc'] !== null)
                            $ {{ number_format((float) $fila['anita_nc'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="num">
                        @if ($fila['diff_nc'] !== null)
                            $ {{ number_format((float) $fila['diff_nc'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Sin filas de conciliación para esta jornada.</p>
    @endif
</body>
</html>
