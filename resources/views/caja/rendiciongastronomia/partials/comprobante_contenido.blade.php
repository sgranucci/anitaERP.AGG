@php
    $d = $datos;
    $totalesTurno = $d['totales_turno'] ?? [];
    $lineas = $d['lineas_medios'] ?? [];
    $resumen = $d['resumen_rendicion'] ?? [];
@endphp

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

<h2>Datos de la rendición</h2>
<table>
    <tr>
        <td class="lbl">Ticket Anita</td>
        <td>{{ $d['codigo_anita'] ?: '—' }}</td>
        <td class="lbl">Rendición #</td>
        <td>{{ $d['rendicion_id'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ $d['empresa_nombre'] }}</td>
        <td class="lbl">Fecha rendición</td>
        <td>{{ $d['fecha_rendicion'] }}</td>
    </tr>
    <tr>
        <td class="lbl">Caja</td>
        <td>{{ $d['caja_nombre'] ?: '—' }}</td>
        <td class="lbl">Registró</td>
        <td>{{ $d['usuario_registro'] ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">PV CAE</td>
        <td>{{ $d['pv_cae_label'] ?? '—' }}</td>
        <td class="lbl">PV CAEA</td>
        <td>{{ $d['pv_caea_label'] ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Inicio del fondo</td>
        <td class="num" colspan="3">${{ number_format((float) ($d['iniciodelfondo'] ?? 0), 2, ',', '.') }}</td>
    </tr>
</table>

<h2>Datos del turno rendido</h2>
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
    <tr>
        <td class="lbl">Cierre definitivo</td>
        <td>{{ $d['cierre_en'] }} — {{ $d['usuario_cierre'] }}</td>
        <td class="lbl">Turno operativo</td>
        <td>#{{ $d['turno_operativo_id'] ?? '—' }}</td>
    </tr>
    @if (!empty($d['observacion_habilitacion']))
    <tr>
        <td class="lbl">Obs. habilitación</td>
        <td colspan="3" class="bloque-obs">{{ $d['observacion_habilitacion'] }}</td>
    </tr>
    @endif
</table>

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
        <td class="lbl">Conciliación turno</td>
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

<p class="muted" style="font-size:11px; margin:0 0 8px;">Cobranzas leídas de cada comprobante emitido en el turno.</p>
@forelse ($totalesTurno['por_mozo'] ?? [] as $m)
    @php
        $mNcTotal = (float) ($m['notas_credito']['total'] ?? 0);
        $mNcCant = (int) ($m['notas_credito']['cantidad'] ?? 0);
        $mMedios = $m['por_medio_pago'] ?? [];
        $mTotalFinal = (float) ($m['total'] ?? 0);
        $mTotalFacturas = isset($m['total_facturas'])
            ? (float) $m['total_facturas']
            : ($mTotalFinal - $mNcTotal);
        $mHayNc = $mNcCant > 0 || abs($mNcTotal) >= 0.005;
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
        @if (! $mHayNc)
    <tr><td colspan="4" class="muted" style="padding-left:16px;">Sin cobranzas en comprobantes de este mozo.</td></tr>
        @endif
    @endforelse
    @if ($mHayNc)
    <tr style="background:#fdecea;">
        <td class="lbl" style="padding-left:16px; color:#922b21;">Notas de crédito ({{ $mNcCant }} comp.)</td>
        <td class="num" colspan="3" style="color:#922b21; font-weight:bold;">${{ number_format($mNcTotal, 2, ',', '.') }}</td>
    </tr>
    @endif
</table>
@empty
<p class="muted">Sin detalle por mozo (totales desde la rendición guardada).</p>
@endforelse

@php
    $ncTotalGlobal = (float) ($totalesTurno['total_notas_credito'] ?? 0);
    $ncCantGlobal = (int) ($totalesTurno['cantidad_notas_credito'] ?? 0);
    $hayNc = $ncCantGlobal > 0 || abs($ncTotalGlobal) >= 0.005;
    $tieneMediosTurno = ! empty($totalesTurno['por_medio_pago']);
@endphp

@if ($tieneMediosTurno || $hayNc)
<h2>Total cobrado en turno por medio de pago</h2>
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
    </tbody>
    <tfoot>
        <tr class="total-grande">
            <td class="lbl">Total cobrado en turno</td>
            <td class="num">${{ number_format((float) ($totalesTurno['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endif

<h2>Medios rendidos en caja</h2>
<p class="muted" style="font-size:11px; margin:0 0 8px;">Importes declarados en la rendición (grilla de caja).</p>
<table>
    <thead>
        <tr>
            <th>Medio de pago</th>
            <th class="num">Monto rendido</th>
            <th class="num">Cotización</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineas as $linea)
        <tr @if (!empty($linea['es_nota_credito'])) style="background:#fdecea;" @endif>
            <td @if (!empty($linea['es_nota_credito'])) style="color:#922b21;font-weight:bold;" @endif>
                {{ $linea['nombre'] ?: ($linea['codigo'] ?: '—') }}
            </td>
            <td class="num" @if (!empty($linea['es_nota_credito'])) style="color:#922b21;font-weight:bold;" @endif>
                ${{ number_format((float) $linea['monto'], 2, ',', '.') }}
            </td>
            <td class="num" @if (!empty($linea['es_nota_credito'])) style="color:#922b21;" @endif>
                ${{ number_format((float) $linea['cotizacion'], 2, ',', '.') }}
            </td>
        </tr>
        @empty
        <tr><td colspan="3" class="muted">Sin movimientos registrados.</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="total-grande">
            <td class="lbl">Total grilla</td>
            <td class="num">${{ number_format((float) ($resumen['total_grilla'] ?? 0), 2, ',', '.') }}</td>
            <td></td>
        </tr>
        <tr class="total-grande">
            <td class="lbl">Total ajustado (rendición)</td>
            <td class="num">${{ number_format((float) ($resumen['total_ajustado'] ?? 0), 2, ',', '.') }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<h2>Ajustes de la rendición</h2>
<table>
    <tr>
        <td class="lbl">Redondeo rendición</td>
        <td class="num">${{ number_format((float) ($d['redondeo_rendicion'] ?? 0), 2, ',', '.') }}</td>
        <td class="lbl">Redondeo invitaciones ($0,01)</td>
        <td class="num">${{ number_format((float) ($d['redondeo_invitaciones'] ?? 0), 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="lbl">Sobrante / faltante</td>
        <td class="num">${{ number_format((float) ($d['sobrante_faltante'] ?? 0), 2, ',', '.') }}</td>
        <td class="lbl">Conciliación rendición</td>
        <td class="num">
            @if (!empty($resumen['cuadra']))
                Cuadra
            @else
                Diferencia: ${{ number_format(abs((float) ($resumen['diferencia'] ?? 0)), 2, ',', '.') }}
            @endif
        </td>
    </tr>
    @if (trim((string) ($d['observacion'] ?? '')) !== '')
    <tr>
        <td class="lbl">Observaciones</td>
        <td colspan="3" class="bloque-obs">{{ $d['observacion'] }}</td>
    </tr>
    @endif
</table>

<p class="muted" style="margin-top:16px;">Documento generado por Anita ERP — Caja / Gastronomía. Uso interno.</p>
