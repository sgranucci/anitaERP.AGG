@php
    $d = $datos;
    $j = $d['jornada'] ?? [];
    $res = $d['resumen'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Saneamiento turnos' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #222; margin: 12px 16px; }
        h1 { font-size: 15px; margin: 0 0 4px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 4px 6px; vertical-align: top; }
        th { background: #f5d76e; font-weight: bold; text-align: left; }
        .cabecera-doc td { border: none !important; vertical-align: middle; }
        .cabecera-doc { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .logo { max-height: 52px; max-width: 180px; }
        .subtitulo { font-size: 10px; color: #444; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #555; font-size: 8px; }
        .alerta { background: #fdecea; border: 1px solid #c0392b; padding: 6px; margin-bottom: 8px; }
        .ok { background: #eafaf1; border: 1px solid #27ae60; padding: 6px; margin-bottom: 8px; }
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
                <h1>{{ $d['titulo'] }}</h1>
                <div class="subtitulo">{{ $d['subtitulo'] }}</div>
                <div class="muted">{{ $d['empresa_nombre'] }} · Emitido: {{ $d['fecha_emision'] }}</div>
                @if (!empty($d['usuario_emision']))
                    <div class="muted">Usuario: {{ $d['usuario_emision'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>Jornada</h2>
    <table>
        <tr>
            <th>ID jornada</th>
            <td>{{ $j['id'] ?? '—' }}</td>
            <th>Fecha jornada</th>
            <td>{{ $j['fecha_jornada_fmt'] ?? $j['fecha_jornada'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Estado</th>
            <td colspan="3">{{ !empty($j['abierta']) ? 'Abierta' : 'Cerrada' }}</td>
        </tr>
    </table>

    <h2>Resumen</h2>
    <table>
        <tr>
            <th>Terminales analizadas</th>
            <td class="num">{{ $res['terminales'] ?? 0 }}</td>
            <th>Facturas huérfanas</th>
            <td class="num">{{ $res['facturas_huerfanas'] ?? 0 }}</td>
        </tr>
        <tr>
            <th>Cuentas pendientes</th>
            <td class="num" colspan="3">{{ $res['cuentas_pendientes'] ?? 0 }}</td>
        </tr>
    </table>

    @foreach ($d['terminales'] ?? [] as $term)
        <h2>Terminal: {{ $term['identificador_pc'] ?? '—' }}</h2>
        @if (($term['cantidad_huerfanas'] ?? 0) > 0)
            <div class="alerta">
                <strong>{{ $term['cantidad_huerfanas'] }}</strong> factura(s) huérfana(s) (fuera de turnos cerrados).
            </div>
        @else
            <div class="ok">Sin facturas huérfanas.</div>
        @endif

        @if (!empty($term['turno_habilitado']))
            <p class="muted">Turno habilitado #{{ $term['turno_operativo_activo_id'] ?? '—' }}</p>
        @endif

        @if (!empty($term['facturas_huerfanas']))
            <table>
                <thead>
                    <tr>
                        <th>Comprobante</th>
                        <th>Emitido</th>
                        <th>Cliente</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($term['facturas_huerfanas'] as $f)
                        <tr>
                            <td>{{ $f['codigo'] ?: '#'.$f['venta_id'] }}</td>
                            <td>{{ $f['emitido_en'] ?? $f['hora'] }}</td>
                            <td>{{ $f['cliente'] }}</td>
                            <td class="num">${{ number_format($f['total'] ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (!empty($term['cuentas_pendientes_detalle']))
            <p><strong>Cuentas pendientes ({{ $term['cuentas_pendientes'] ?? 0 }})</strong>
                @if (!empty($term['confirmacion_cierre_cuentas']))
                    — Confirmación cierre: <code>{{ $term['confirmacion_cierre_cuentas'] }}</code>
                @endif
            </p>
            <p class="muted" style="font-size: 9pt; margin-bottom: 6px;">
                Apertura = fecha de creación de la cuenta. «Cerrada sin facturar» no aparece como mesa ocupada en el facturador.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Referencia</th>
                        <th>Apertura</th>
                        <th>Estado</th>
                        <th class="num">Ítems</th>
                        <th>Mozo</th>
                        <th class="num">Subtotal est.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($term['cuentas_pendientes_detalle'] as $c)
                        <tr>
                            <td>{{ $c['id'] }}</td>
                            <td>{{ $c['etiqueta'] }}</td>
                            <td>{{ $c['apertura_en_fmt'] ?? ($c['apertura_en'] ?? '—') }}</td>
                            <td>{{ $c['estado_etiqueta'] ?? $c['estado'] }}</td>
                            <td class="num">
                                @if (!empty($c['tiene_items']))
                                    {{ $c['lineas'] }}
                                @else
                                    0 (vacía)
                                @endif
                            </td>
                            <td>{{ $c['mozo'] ?? '—' }}</td>
                            <td class="num">${{ number_format($c['subtotal'] ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (!empty($term['turnos']))
            <p class="muted"><strong>Turnos cerrados en jornada</strong></p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Turno</th>
                        <th>Habilitación</th>
                        <th>Cierre</th>
                        <th class="num">Facturación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($term['turnos'] as $t)
                        <tr>
                            <td>{{ $t['id'] }}</td>
                            <td>{{ $t['turno_nombre'] }}</td>
                            <td>{{ $t['habilitacion_en'] }}</td>
                            <td>{{ $t['cierre_en'] }}</td>
                            <td class="num">${{ number_format($t['monto_facturacion_turno'] ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (!empty($term['sugerencias']))
            <p><strong>Sugerencias</strong></p>
            <ul style="margin: 0; padding-left: 16px;">
                @foreach ($term['sugerencias'] as $s)
                    <li>{{ $s['detalle'] ?? '' }}</li>
                @endforeach
            </ul>
        @endif
    @endforeach

    <p class="muted" style="margin-top: 16px;">
        Documento generado desde Saneamiento turnos gastronomía. No modifica comprobantes fiscales.
    </p>
</body>
</html>
