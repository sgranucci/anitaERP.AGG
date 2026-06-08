@php
    $arqueoMedios = ! empty($totalesTurno['arqueo_medios_cierre']);
    $tituloMedios = $tituloMedios ?? 'Total final por medio de pago';
    $etiquetaTotal = $etiquetaTotal ?? 'Total';
    $totalEsperadoSistema = (float) ($totalesTurno['total_cobrado'] ?? 0);
    $totalContadoCajero = (float) ($totalesTurno['total_cobrado_contado'] ?? $totalEsperadoSistema);
@endphp
<h2>{{ $tituloMedios }}</h2>
@if ($arqueoMedios)
<p class="muted" style="font-size:11px; margin:0 0 8px;">
    Montos declarados por el cajero al cierre definitivo del turno, comparados con el sistema.
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
        @foreach ($totalesTurno['por_medio_pago'] ?? [] as $p)
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
