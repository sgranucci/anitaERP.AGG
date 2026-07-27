@php
    $arqueoMedios = ! empty($totalesTurno['arqueo_medios_cierre']);
    $tituloMedios = $tituloMedios ?? 'Medios a rendir en caja';
    $etiquetaTotal = $etiquetaTotal ?? 'Total a rendir';
    $facturacionTotem = is_array($totalesTurno['facturacion_totem'] ?? null) ? $totalesTurno['facturacion_totem'] : null;
    $totalTotem = (float) ($facturacionTotem['total'] ?? $totalesTurno['total_facturacion_totem'] ?? 0);
    $hayTotem = $facturacionTotem !== null || abs($totalTotem) >= 0.005;
    $totalEsperadoSistema = (float) ($totalesTurno['total_cobrado_a_rendir']
        ?? ((float) ($totalesTurno['total_cobrado'] ?? 0) - $totalTotem));
    $totalContadoCajero = (float) ($totalesTurno['total_cobrado_contado'] ?? $totalEsperadoSistema);
    $mediosArqueo = [];
    foreach ($totalesTurno['por_medio_pago'] ?? [] as $p) {
        if (! is_array($p)) {
            continue;
        }
        if (! empty($p['excluido_arqueo']) || ! empty($p['es_facturacion_totem'])) {
            continue;
        }
        $mediosArqueo[] = $p;
    }
@endphp
<h2>{{ $tituloMedios }}</h2>
@if ($arqueoMedios)
    <p class="muted" style="font-size:11px; margin:0 0 8px;">
        Montos que el cajero debe entregar o declarar. TOTEM no entra al arqueo (ya cobrado en el kiosco).
    </p>
@endif
<table>
    <thead>
        <tr>
            <th>Medio de pago</th>
            @if ($arqueoMedios)
                <th class="num">Esperado sistema</th>
                <th class="num">Contado cajero</th>
            @else
                <th class="num">Cobrado</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($mediosArqueo as $p)
            @php
                $esperado = (float) ($p['esperado'] ?? $p['total'] ?? 0);
                $contado = array_key_exists('contado', $p) ? (float) $p['contado'] : $esperado;
                $diff = round($contado - $esperado, 2);
            @endphp
        <tr>
            <td>{{ $p['nombre'] ?? $p['codigo'] ?? '—' }}</td>
            @if ($arqueoMedios)
                <td class="num">${{ number_format($esperado, 2, ',', '.') }}</td>
                <td class="num" @if (abs($diff) >= 0.02) style="font-weight:bold; color:#856404;" @endif>
                    ${{ number_format($contado, 2, ',', '.') }}
                    @if (abs($diff) >= 0.02)
                        <br><span style="font-size:9px;">Δ ${{ number_format(abs($diff), 2, ',', '.') }}
                            @if ($diff > 0)
                                (sobra)
                            @else
                                (falta)
                            @endif
                        </span>
                    @endif
                </td>
            @else
                <td class="num">${{ number_format($esperado, 2, ',', '.') }}</td>
            @endif
        </tr>
        @endforeach
        @if ($hayNc ?? false)
        <tr style="background:#fdecea;">
            <td style="color:#922b21; font-weight:bold;">Notas de crédito ({{ $ncCantGlobal }})</td>
            @if ($arqueoMedios)
                <td class="num" style="color:#999;">—</td>
                <td class="num" style="color:#922b21; font-weight:bold;">${{ number_format($ncTotalGlobal, 2, ',', '.') }}</td>
            @else
                <td class="num" style="color:#922b21; font-weight:bold;">${{ number_format($ncTotalGlobal, 2, ',', '.') }}</td>
            @endif
        </tr>
        @endif
        @if ($hayInv ?? false)
        <tr style="background:#fff8e1;">
            <td style="color:#856404; font-weight:bold;">Invitaciones $0,01 ({{ $invCantGlobal }})</td>
            @if ($arqueoMedios)
                <td class="num" style="color:#999;">—</td>
                <td class="num" style="color:#856404; font-weight:bold;">${{ number_format($invTotalGlobal, 2, ',', '.') }}</td>
            @else
                <td class="num" style="color:#856404; font-weight:bold;">${{ number_format($invTotalGlobal, 2, ',', '.') }}</td>
            @endif
        </tr>
        @endif
    </tbody>
    <tfoot>
        <tr class="total-grande">
            <td class="lbl">{{ $etiquetaTotal }}</td>
            @if ($arqueoMedios)
                <td class="num">${{ number_format($totalEsperadoSistema, 2, ',', '.') }}</td>
                <td class="num">${{ number_format($totalContadoCajero, 2, ',', '.') }}</td>
            @else
                <td class="num">${{ number_format($totalEsperadoSistema, 2, ',', '.') }}</td>
            @endif
        </tr>
    </tfoot>
</table>

@if ($hayTotem)
    <h2 style="margin-top:14px;">Facturación TOTEM — no entregar en caja</h2>
    <p class="muted" style="font-size:11px; margin:0 0 8px;">
        {{ $facturacionTotem['leyenda'] ?? 'Comandas Waitry ya cobradas en el tótem/kiosco. Integran la facturación del turno, pero el dinero no está en la gaveta.' }}
    </p>
    <table>
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background:#eef7fb;">
                <td>{{ $facturacionTotem['nombre'] ?? $facturacionTotem['codigo'] ?? 'TOTEM' }} (puente contable)</td>
                <td class="num" style="font-weight:bold;">${{ number_format($totalTotem, 2, ',', '.') }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td class="lbl">Cobrado neto del turno (caja + TOTEM)</td>
                <td class="num">${{ number_format((float) ($totalesTurno['total_cobrado'] ?? 0), 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
@endif
