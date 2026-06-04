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
        .bloque-obs { white-space: pre-wrap; word-wrap: break-word; word-break: break-word; font-size: 8px; }
        .celda-anulaciones { padding: 0 !important; }
        .tabla-anulaciones { width: 100%; margin: 0; border-collapse: collapse; font-size: 7px; }
        .tabla-anulaciones th { background: #f5f5f5; font-weight: bold; padding: 2px 4px; border: none; border-bottom: 1px solid #999; }
        .tabla-anulaciones td { border: none; border-bottom: 1px dotted #ccc; padding: 2px 4px; vertical-align: top; }
        .motivo-anulacion { word-wrap: break-word; word-break: break-word; }
        .total-grande { font-size: 12px; font-weight: bold; background: #e8f4fc; }
    </style>
</head>
<body>
    @if (!empty($d['solo_totales_mozo']))
    <table style="width:100%; margin-bottom:14px; border:3px solid #c0392b; background:#fdecea;">
        <tr>
            <td style="padding:10px 12px; text-align:center; font-size:13px; font-weight:bold; color:#922b21;">
                INFORME INFORMATIVO — NO CIERRA EL TURNO
                <br>
                <span style="font-size:10px; font-weight:normal;">
                    Solo totales por mozo. El turno permanece habilitado; no reemplaza un cierre parcial ni definitivo.
                </span>
            </td>
        </tr>
    </table>
    @endif

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
            <td class="lbl">Nº cierre empresa</td>
            <td>
                @if (!empty($d['numero_cierre']))
                    #{{ (int) $d['numero_cierre'] }}
                    <span class="muted">(registro interno #{{ (int) ($d['turno_operativo_id'] ?? 0) }})</span>
                @elseif (($d['tipo'] ?? '') === 'parcial')
                    Cierre pendiente
                    <span class="muted">(registro interno #{{ (int) ($d['turno_operativo_id'] ?? 0) }})</span>
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Fecha jornada</td>
            <td colspan="3">{{ $d['fecha_jornada'] }}</td>
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
        @include('ventas.gastronomia.cierres_turno.partials.observacion_habilitacion_comprobante', ['d' => $d])
    </table>

    @include('ventas.gastronomia.cierres_turno.partials.numeracion_fiscal_turno', ['d' => $d])

    @php
        $totalFinalCabecera = (float) ($totalesTurno['total_ventas'] ?? $totalesTurno['total_general'] ?? 0);
        $ncTotalCab = (float) ($totalesTurno['total_notas_credito'] ?? 0);
        $facturasBrutoCab = isset($totalesTurno['total_facturas'])
            ? (float) $totalesTurno['total_facturas']
            : ($totalFinalCabecera - $ncTotalCab);
        $cantFacturasCab = (int) ($totalesTurno['cantidad_facturas']
            ?? ((int) ($totalesTurno['cantidad_comprobantes'] ?? 0) - (int) ($totalesTurno['cantidad_notas_credito'] ?? 0)));
        $cantNcCab = (int) ($totalesTurno['cantidad_notas_credito'] ?? 0);
    @endphp

    @if (empty($d['solo_totales_mozo']))
    <h2>Facturación del turno (esta terminal)</h2>
    <table>
        <tr>
            <td class="lbl">Facturado bruto <em>(sin NC)</em></td>
            <td class="num">${{ number_format($facturasBrutoCab, 2, ',', '.') }}</td>
            <td class="lbl">Facturas emitidas</td>
            <td class="num">{{ $cantFacturasCab }}</td>
        </tr>
        <tr>
            <td class="lbl">Invitaciones $0,01 sin cobranza</td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_invitaciones'] ?? 0), 2, ',', '.') }} ({{ (int) ($totalesTurno['cantidad_invitaciones'] ?? 0) }} comp.)</td>
            <td class="lbl">Notas de crédito (devoluciones)</td>
            <td class="num" style="color:#922b21;">${{ number_format($ncTotalCab, 2, ',', '.') }} ({{ $cantNcCab }} comp.)</td>
        </tr>
        <tr class="total-grande">
            <td class="lbl">Facturado final <em>(con NC restadas)</em></td>
            <td class="num">${{ number_format($totalFinalCabecera, 2, ',', '.') }}</td>
            <td class="lbl">Comprobantes</td>
            <td class="num">{{ (int) ($totalesTurno['cantidad_comprobantes'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="lbl">Ventas con cobranza esperada <em>(final − invitaciones)</em></td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_ventas_cobrables'] ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Cobrado neto <em>(cobranzas − devoluciones NC)</em></td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Conciliación</td>
            <td class="num" colspan="3">
                @if (!empty($totalesTurno['conciliacion_ok']))
                    Cuadra
                @else
                    Diferencia: ${{ number_format(abs((float) ($totalesTurno['diferencia_cobranza'] ?? 0)), 2, ',', '.') }}
                    @if ((float) ($totalesTurno['diferencia_cobranza'] ?? 0) > 0)
                        (sobra en caja)
                    @elseif ((float) ($totalesTurno['diferencia_cobranza'] ?? 0) < 0)
                        (falta en caja)
                    @endif
                @endif
            </td>
        </tr>
    </table>

    @else
    <h2>Totales por mozo (turno en curso)</h2>
    <p class="muted" style="font-size:11px; margin:0 0 8px;">
        Documento de consulta. No registra cierre parcial ni definitivo salvo que se indique en el sistema.
    </p>
    @endif

    @if (!empty($d['solo_totales_mozo']))
    <p class="muted" style="font-size:11px; margin:0 0 8px;">Cobranzas por mozo desde la habilitación del turno.</p>
    @else
    <p class="muted" style="font-size:11px; margin:0 0 8px;">Cobranzas leídas de cada comprobante emitido.</p>
    @endif
    @forelse ($totalesTurno['por_mozo'] ?? [] as $m)
        @php
            $mNcTotal = (float) ($m['notas_credito']['total'] ?? 0);
            $mNcCant = (int) ($m['notas_credito']['cantidad'] ?? 0);
            $mInvTotal = (float) ($m['invitaciones']['total'] ?? 0);
            $mInvCant = (int) ($m['invitaciones']['cantidad'] ?? 0);
            $mMedios = $m['por_medio_pago'] ?? [];
            $mTotalFinal = (float) ($m['total'] ?? 0);
            $mTotalFacturas = isset($m['total_facturas'])
                ? (float) $m['total_facturas']
                : ($mTotalFinal - $mNcTotal);
            $mHayNc = $mNcCant > 0 || abs($mNcTotal) >= 0.005;
            $mHayInv = $mInvCant > 0 || abs($mInvTotal) >= 0.005;
        @endphp
    <table style="margin-bottom:10px;">
        <tr class="total-grande">
            <td class="lbl" colspan="2">{{ $m['mozo_nombre'] ?? 'Sin mozo' }}</td>
            <td class="num">{{ (int) ($m['cantidad'] ?? 0) }} comp.</td>
            <td class="num">
                @if ($mHayNc)
                    Fact. final ${{ number_format($mTotalFinal, 2, ',', '.') }}
                    <span style="font-size:9px;">(bruto ${{ number_format($mTotalFacturas, 2, ',', '.') }} − NC ${{ number_format(abs($mNcTotal), 2, ',', '.') }})</span>
                @else
                    Fact. ${{ number_format($mTotalFinal, 2, ',', '.') }}
                @endif
                · Cob. ${{ number_format((float) ($m['total_cobrado'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
        @forelse ($mMedios as $p)
        <tr>
            <td class="lbl" style="padding-left:16px;">{{ $p['nombre'] ?? $p['codigo'] ?? '—' }}</td>
            <td class="num" colspan="3">${{ number_format((float) ($p['total'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        @empty
            @if (! $mHayNc && ! $mHayInv)
        <tr><td colspan="4" class="muted" style="padding-left:16px;">Sin cobranzas en comprobantes de este mozo.</td></tr>
            @endif
        @endforelse
        @if ($mHayNc)
        <tr style="background:#fdecea;">
            <td class="lbl" style="padding-left:16px; color:#922b21;">Notas de crédito ({{ $mNcCant }} comp.)</td>
            <td class="num" colspan="3" style="color:#922b21; font-weight:bold;">${{ number_format($mNcTotal, 2, ',', '.') }}</td>
        </tr>
        @endif
        @if ($mHayInv)
        <tr style="background:#fff8e1;">
            <td class="lbl" style="padding-left:16px; color:#856404;">Invitaciones $0,01 ({{ $mInvCant }} comp.)</td>
            <td class="num" colspan="3" style="color:#856404; font-weight:bold;">${{ number_format($mInvTotal, 2, ',', '.') }}</td>
        </tr>
        @endif
    </table>
    @empty
    <p class="muted">Sin comprobantes por mozo.</p>
    @endforelse

    @php
        $ncTotalGlobal = (float) ($totalesTurno['total_notas_credito'] ?? 0);
        $ncCantGlobal = (int) ($totalesTurno['cantidad_notas_credito'] ?? 0);
        $invTotalGlobal = (float) ($totalesTurno['total_invitaciones'] ?? 0);
        $invCantGlobal = (int) ($totalesTurno['cantidad_invitaciones'] ?? 0);
        $hayNc = $ncCantGlobal > 0 || abs($ncTotalGlobal) >= 0.005;
        $hayInv = $invCantGlobal > 0 || abs($invTotalGlobal) >= 0.005;
        $tieneMedios = ! empty($totalesTurno['por_medio_pago']);
    @endphp

    @if (!empty($d['solo_totales_mozo']))
        @php
            $totalFinalResumen = (float) ($totalesTurno['total_ventas'] ?? $totalesTurno['total_general'] ?? 0);
            $facturasBrutoResumen = isset($totalesTurno['total_facturas'])
                ? (float) $totalesTurno['total_facturas']
                : ($totalFinalResumen - $ncTotalGlobal);
        @endphp
    <h2>Resumen general del turno (al momento del informe)</h2>
    <p class="muted" style="font-size:11px; margin:0 0 8px;">
        Totales acumulados de la terminal desde la habilitación, consolidados por medio de pago.
    </p>
    <table>
        <tr>
            <td class="lbl">Facturado bruto <em>(sin NC)</em></td>
            <td class="num">${{ number_format($facturasBrutoResumen, 2, ',', '.') }}</td>
            <td class="lbl">Facturas emitidas</td>
            <td class="num">{{ (int) ($totalesTurno['cantidad_facturas'] ?? ((int) ($totalesTurno['cantidad_comprobantes'] ?? 0) - $ncCantGlobal)) }}</td>
        </tr>
        <tr>
            <td class="lbl">Invitaciones $0,01 sin cobranza</td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_invitaciones'] ?? 0), 2, ',', '.') }} ({{ (int) ($totalesTurno['cantidad_invitaciones'] ?? 0) }} comp.)</td>
            <td class="lbl">Notas de crédito (devoluciones)</td>
            <td class="num" style="color:#922b21;">${{ number_format($ncTotalGlobal, 2, ',', '.') }} ({{ $ncCantGlobal }} comp.)</td>
        </tr>
        <tr class="total-grande">
            <td class="lbl">Facturado final <em>(con NC restadas)</em></td>
            <td class="num">${{ number_format($totalFinalResumen, 2, ',', '.') }}</td>
            <td class="lbl">Comprobantes</td>
            <td class="num">{{ (int) ($totalesTurno['cantidad_comprobantes'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="lbl">Ventas con cobranza esperada <em>(final − invitaciones)</em></td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_ventas_cobrables'] ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Cobrado neto <em>(cobranzas − devoluciones NC)</em></td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Conciliación</td>
            <td class="num" colspan="3">
                @if (!empty($totalesTurno['conciliacion_ok']))
                    Cuadra
                @else
                    Diferencia: ${{ number_format(abs((float) ($totalesTurno['diferencia_cobranza'] ?? 0)), 2, ',', '.') }}
                    @if ((float) ($totalesTurno['diferencia_cobranza'] ?? 0) > 0)
                        (sobra en caja)
                    @elseif ((float) ($totalesTurno['diferencia_cobranza'] ?? 0) < 0)
                        (falta en caja)
                    @endif
                @endif
            </td>
        </tr>
    </table>

    @if ($tieneMedios || $hayNc || $hayInv)
    <h2>Total general por medio de pago</h2>
    <table>
        <thead>
            <tr>
                <th>Medio de pago</th>
                <th class="num">Cobrado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($totalesTurno['por_medio_pago'] ?? [] as $p)
            <tr>
                <td>{{ $p['nombre'] ?? $p['codigo'] ?? '—' }}</td>
                <td class="num">${{ number_format((float) ($p['total'] ?? 0), 2, ',', '.') }}</td>
            </tr>
            @endforeach
            @if ($hayNc)
            <tr style="background:#fdecea;">
                <td style="color:#922b21; font-weight:bold;">Notas de crédito ({{ $ncCantGlobal }})</td>
                <td class="num" style="color:#922b21; font-weight:bold;">${{ number_format($ncTotalGlobal, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if ($hayInv)
            <tr style="background:#fff8e1;">
                <td style="color:#856404; font-weight:bold;">Invitaciones $0,01 ({{ $invCantGlobal }})</td>
                <td class="num" style="color:#856404; font-weight:bold;">${{ number_format($invTotalGlobal, 2, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
        <tfoot>
            <tr class="total-grande">
                <td class="lbl">Total cobrado en turno</td>
                <td class="num">${{ number_format((float) ($totalesTurno['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
    @endif
    @endif

    @if (empty($d['solo_totales_mozo']) && ($tieneMedios || $hayNc || $hayInv))
    @include('ventas.gastronomia.cierres_turno.partials.comprobante_medios_pago_cierre', [
        'totalesTurno' => $totalesTurno,
        'hayNc' => $hayNc,
        'hayInv' => $hayInv,
        'ncTotalGlobal' => $ncTotalGlobal,
        'ncCantGlobal' => $ncCantGlobal,
        'invTotalGlobal' => $invTotalGlobal,
        'invCantGlobal' => $invCantGlobal,
        'tituloMedios' => 'Total final por medio de pago',
        'etiquetaTotal' => 'Total',
    ])
    @endif

    @if (empty($d['solo_totales_mozo']) && $totalesDia !== null)
        @php
            $totalFinalDia = (float) ($totalesDia['total_ventas'] ?? $totalesDia['total_general'] ?? 0);
            $ncDia = (float) ($totalesDia['total_notas_credito'] ?? 0);
            $hayNcDia = (int) ($totalesDia['cantidad_notas_credito'] ?? 0) > 0 || abs($ncDia) >= 0.005;
            $facturasBrutoDia = isset($totalesDia['total_facturas'])
                ? (float) $totalesDia['total_facturas']
                : ($totalFinalDia - $ncDia);
        @endphp
    <h2>Acumulado del día (esta terminal, jornada)</h2>
    <table>
        @if ($hayNcDia)
        <tr>
            <td class="lbl">Facturado bruto día <em>(sin NC)</em></td>
            <td class="num">${{ number_format($facturasBrutoDia, 2, ',', '.') }}</td>
            <td class="lbl">Notas de crédito día</td>
            <td class="num" style="color:#922b21;">${{ number_format($ncDia, 2, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="total-grande">
            <td class="lbl">{{ $hayNcDia ? 'Facturado final día (con NC restadas)' : 'Total acumulado día' }}</td>
            <td class="num" colspan="3">${{ number_format($totalFinalDia, 2, ',', '.') }}</td>
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
            <td class="num" colspan="3">${{ number_format((float) ($d['sobrante_faltante'] ?? 0), 2, ',', '.') }}</td>
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
