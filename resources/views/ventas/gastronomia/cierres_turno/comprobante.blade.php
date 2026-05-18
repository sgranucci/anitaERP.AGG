@php
    $d = $datos;
    $totalesTurno = $d['totales_turno'] ?? [];
    $totalesDia = $d['totales_dia'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Cierre turno' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #222; margin: 12px 16px; }
        h1 { font-size: 16px; margin: 0 0 4px 0; color: #1a1a1a; }
        h2 { font-size: 11px; margin: 12px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #666; padding: 4px 6px; vertical-align: top; }
        th { background: #d4e6f1; font-weight: bold; text-align: left; }
        .cabecera-doc td { border: none !important; vertical-align: middle; }
        .cabecera-doc { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #333; padding-bottom: 8px; }
        .logo { max-height: 56px; max-width: 200px; }
        .subtitulo { font-size: 10px; color: #444; margin-bottom: 8px; }
        .lbl { background: #f0f0f0; font-weight: bold; width: 28%; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #555; font-size: 8px; }
        .bloque-obs { white-space: pre-wrap; min-height: 24px; }
        .total-grande { font-size: 12px; font-weight: bold; background: #e8f4fc; }
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
                <div class="muted">Emitido: {{ $d['fecha_emision_comprobante'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Datos del turno</h2>
    <table>
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $d['empresa_nombre'] }}</td>
            <td class="lbl">Terminal (PC)</td>
            <td>{{ $d['identificador_pc'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Turno</td>
            <td>{{ $d['turno_catalogo'] }} @if(($d['turno_horario'] ?? '—') !== '—') ({{ $d['turno_horario'] }}) @endif</td>
            <td class="lbl">Fecha jornada</td>
            <td>{{ $d['fecha_jornada'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Habilitación</td>
            <td>{{ $d['habilitacion_en'] }} — {{ $d['usuario_habilita'] }} → {{ $d['usuario_habilitado'] }}</td>
            <td class="lbl">Monto habilitación</td>
            <td class="num">${{ number_format((float) ($d['monto_habilitacion'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        @if (($d['tipo'] ?? '') === 'cierre')
        <tr>
            <td class="lbl">Cierre definitivo</td>
            <td>{{ $d['cierre_en'] }} — {{ $d['usuario_registro'] }}</td>
            <td class="lbl">Cierres parciales previos</td>
            <td>{{ (int) ($d['cantidad_parciales'] ?? 0) }}</td>
        </tr>
        @else
        <tr>
            <td class="lbl">Registrado por</td>
            <td colspan="3">{{ $d['usuario_registro'] }}</td>
        </tr>
        @endif
        @if (!empty($d['observacion_habilitacion']))
        <tr>
            <td class="lbl">Obs. habilitación</td>
            <td colspan="3" class="bloque-obs">{{ $d['observacion_habilitacion'] }}</td>
        </tr>
        @endif
    </table>

    <h2>Facturación del turno (esta terminal)</h2>
    <table>
        <tr class="total-grande">
            <td class="lbl">Total facturado turno</td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_general'] ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Comprobantes</td>
            <td class="num">{{ (int) ($totalesTurno['cantidad_comprobantes'] ?? 0) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:50%;">Por mozo</th>
                <th style="width:50%;">Por medio de pago</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <table style="border:none;">
                        @forelse ($totalesTurno['por_mozo'] ?? [] as $m)
                        <tr>
                            <td style="border:none; border-bottom:1px solid #ddd;">{{ $m['mozo_nombre'] ?? '—' }}</td>
                            <td style="border:none; border-bottom:1px solid #ddd;" class="num">${{ number_format((float) ($m['total'] ?? 0), 2, ',', '.') }} ({{ (int) ($m['cantidad'] ?? 0) }})</td>
                        </tr>
                        @empty
                        <tr><td style="border:none;" class="muted">Sin datos</td></tr>
                        @endforelse
                    </table>
                </td>
                <td>
                    <table style="border:none;">
                        @forelse ($totalesTurno['por_medio_pago'] ?? [] as $p)
                        <tr>
                            <td style="border:none; border-bottom:1px solid #ddd;">{{ $p['nombre'] ?? $p['codigo'] ?? '—' }}</td>
                            <td style="border:none; border-bottom:1px solid #ddd;" class="num">${{ number_format((float) ($p['total'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr><td style="border:none;" class="muted">Sin datos</td></tr>
                        @endforelse
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    @if ($totalesDia !== null)
    <h2>Acumulado del día (esta terminal, jornada)</h2>
    <table>
        <tr class="total-grande">
            <td class="lbl">Total acumulado día</td>
            <td class="num" colspan="3">${{ number_format((float) ($totalesDia['total_general'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </table>
    @endif

    @if (($d['tipo'] ?? '') === 'cierre')
    <h2>Ajustes de cierre</h2>
    <table>
        <tr>
            <td class="lbl">Redondeo invitaciones ($0,01)</td>
            <td class="num">${{ number_format((float) ($d['redondeo_invitaciones'] ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Redondeo turno</td>
            <td class="num">${{ number_format((float) ($d['redondeo_turno'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Sobrante / faltante</td>
            <td class="num">${{ number_format((float) ($d['sobrante_faltante'] ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Sugerido invitaciones</td>
            <td class="num">${{ number_format((float) ($totalesTurno['redondeo_invitaciones_sugerido'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        @if (!empty($d['observacion_cierre']))
        <tr>
            <td class="lbl">Observaciones cierre</td>
            <td colspan="3" class="bloque-obs">{{ $d['observacion_cierre'] }}</td>
        </tr>
        @endif
    </table>
    @endif

    <p class="muted" style="margin-top:16px;">Documento generado por Anita ERP — Gastronomía. Uso interno.</p>
</body>
</html>
