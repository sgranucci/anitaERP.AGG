@php
    $totalCartones = round((float) ($total_cartones ?? 0), 2);
    $cantCartones = (int) ($cant_cartones ?? 0);
    $cartones = is_array($cartones ?? null) ? $cartones : [];
    $conceptosRaw = $conceptos ?? [];
    if (is_array($conceptosRaw) && isset($conceptosRaw['lineas']) && is_array($conceptosRaw['lineas'])) {
        $conceptosLineas = $conceptosRaw['lineas'];
    } elseif (is_array($conceptosRaw) && array_is_list($conceptosRaw)) {
        $conceptosLineas = $conceptosRaw;
    } else {
        $conceptosLineas = [];
    }
    $saldoFinal = round((float) ($saldo_final ?? 0), 2);
    if ($saldoFinal <= 0 && $conceptosLineas !== []) {
        $ultima = end($conceptosLineas);
        $saldoFinal = round((float) ($ultima['saldo_despues'] ?? 0), 2);
    }
    $mostrarCartones = ($mostrar_cartones ?? true) && $cartones !== [];
    $mostrarAjustes = (bool) ($mostrar_ajustes ?? true);

    $lineasOperativas = [];
    foreach ($conceptosLineas as $linea) {
        if (! is_array($linea) || ! empty($linea['es_saldo_rendicion'])) {
            continue;
        }
        $lineasOperativas[] = $linea;
    }

    $mostrarColumnaSaldo = false;
    foreach ($lineasOperativas as $linea) {
        if (round((float) ($linea['monto'] ?? 0), 2) !== 0.0) {
            $mostrarColumnaSaldo = true;
            break;
        }
    }

    $colspanConceptos = $mostrarColumnaSaldo ? 4 : 3;
    $colspanSaldoFinal = $mostrarColumnaSaldo ? 3 : 2;
@endphp

<div class="bingo-recaudacion-origen">
    <span class="etiqueta">Total recaudaci&oacute;n (base de c&aacute;lculo &mdash; valor de origen)</span>
    <span class="valor">${{ number_format($totalCartones, 2, ',', '.') }}</span>
    @if ($cantCartones > 0)
        <span class="muted" style="display:block;margin-top:4px;">{{ number_format($cantCartones, 0, ',', '.') }} cartones vendidos</span>
    @endif
</div>

@if ($mostrarCartones)
<h2>Cartones vendidos</h2>
<table>
    <thead>
        <tr>
            <th>C&oacute;digo</th>
            <th>Descripci&oacute;n</th>
            <th class="num">Cant.</th>
            <th class="num">Precio unit.</th>
            <th class="num">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($cartones as $linea)
            @php
                $cant = max(0, (int) ($linea['cantidad'] ?? 0));
                $precio = (float) ($linea['precio_unitario'] ?? 0);
                $subtotal = round($cant * $precio, 2);
            @endphp
            @if (! empty($linea['anulado']))
                @continue
            @endif
            <tr>
                <td>{{ $linea['codigo'] ?? '' }}</td>
                <td>{{ $linea['nombre'] ?? '' }}</td>
                <td class="num">{{ $cant }}</td>
                <td class="num">${{ number_format($precio, 2, ',', '.') }}</td>
                <td class="num">${{ number_format($subtotal, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-grande">
            <td colspan="4" class="lbl">Total recaudaci&oacute;n cartones</td>
            <td class="num">${{ number_format($totalCartones, 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endif

<h2>Cuenta de rendici&oacute;n</h2>
@if ($mostrarColumnaSaldo)
<p class="muted" style="margin:0 0 6px;">Cada concepto se aplica sobre el saldo anterior; la columna <strong>Saldo</strong> muestra el resultado acumulado.</p>
@endif
<table class="tabla-cuenta">
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="num">%</th>
            <th class="num">Monto</th>
            @if ($mostrarColumnaSaldo)
                <th class="num">Saldo</th>
            @endif
        </tr>
    </thead>
    <tbody>
        <tr class="fila-origen">
            <td>Recaudaci&oacute;n cartones</td>
            <td class="num"></td>
            <td class="num">${{ number_format($totalCartones, 2, ',', '.') }}</td>
            @if ($mostrarColumnaSaldo)
                <td class="num">${{ number_format($totalCartones, 2, ',', '.') }}</td>
            @endif
        </tr>
        @forelse ($lineasOperativas as $linea)
            @php
                $signo = ($linea['signo'] ?? '') === '+' ? '+' : '&minus;';
                $detalle = $linea['detalle'] ?? '';
                $pct = isset($linea['porcentaje']) ? number_format((float) $linea['porcentaje'], 2, ',', '.') : '';
                $monto = round((float) ($linea['monto'] ?? 0), 2);
                $saldoDespues = isset($linea['saldo_despues']) ? round((float) $linea['saldo_despues'], 2) : null;
                $montoVacio = $monto === 0.0;
            @endphp
            <tr>
                <td>{!! $signo !!} {{ $detalle }}</td>
                <td class="num">{{ $pct !== '' ? $pct.'%' : '' }}</td>
                <td class="num">
                    @if (! $montoVacio)
                        ${{ number_format($monto, 2, ',', '.') }}
                    @endif
                </td>
                @if ($mostrarColumnaSaldo)
                    <td class="num">
                        @if (! $montoVacio && $saldoDespues !== null)
                            ${{ number_format($saldoDespues, 2, ',', '.') }}
                        @endif
                    </td>
                @endif
            </tr>
        @empty
            @if ($mostrarColumnaSaldo)
                <tr><td colspan="{{ $colspanConceptos }}" class="muted">Sin conceptos de rendici&oacute;n registrados.</td></tr>
            @endif
        @endforelse
    </tbody>
    <tfoot>
        <tr class="fila-saldo-final">
            <td colspan="{{ $colspanSaldoFinal }}" class="lbl">Saldo rendici&oacute;n / dep&oacute;sito</td>
            <td class="num">${{ number_format((float) ($deposito ?? $saldoFinal), 2, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>

@php
    $hayAjustes = ((float) ($redondeo ?? 0)) !== 0.0
        || ((float) ($sobrante_faltante ?? 0)) !== 0.0
        || ((float) ($vales ?? 0)) !== 0.0;
@endphp
@if ($mostrarAjustes && $hayAjustes)
<h2>Ajustes</h2>
<table>
    <tr>
        <td class="lbl">Redondeo</td>
        <td class="num">${{ number_format((float) ($redondeo ?? 0), 2, ',', '.') }}</td>
        <td class="lbl">Sobrante / faltante</td>
        <td class="num">${{ number_format((float) ($sobrante_faltante ?? 0), 2, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="lbl">Vales</td>
        <td class="num">${{ number_format((float) ($vales ?? 0), 2, ',', '.') }}</td>
        <td colspan="2"></td>
    </tr>
</table>
@endif
