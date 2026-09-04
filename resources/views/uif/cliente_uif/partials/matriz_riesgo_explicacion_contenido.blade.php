@php
    $cliente = $reporte['cliente'] ?? null;
    $factoresFicha = $reporte['factores_ficha'] ?? [];
    $umbrales = $reporte['umbrales'] ?? [];
    $periodos = $reporte['periodos'] ?? [];
    $generado = $reporte['generado'] ?? date('d/m/Y H:i');
    $esExcel = ! empty($esExcel);
@endphp

<p style="margin: 4px 0 8px;">
    Fórmula: valor = puntaje × ponderación% / 100. Suma de los 9 factores → clasificación por umbrales.
    Premios del período: excluye día 1 y último día del mes (comparación estricta del ERP).
</p>

@if (count($umbrales) > 0)
<table class="data" style="width: 55%; margin-bottom: 12px;">
    <thead>
        <tr>
            <th>Desde</th>
            <th>Hasta</th>
            <th>Riesgo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($umbrales as $u)
            <tr>
                <td>{{ $u['desde'] }}</td>
                <td>{{ $u['hasta'] }}</td>
                <td>{{ $u['riesgo'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

<h3 style="margin: 12px 0 6px; font-size: {{ $esExcel ? '12' : '11' }}px;">Factores de ficha del cliente</h3>
<table class="data">
    <thead>
        <tr>
            <th>Factor</th>
            <th>Valor</th>
            <th style="text-align: right;">Puntaje</th>
            <th style="text-align: right;">Pond. %</th>
            <th style="text-align: right;">Contribución</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($factoresFicha as $f)
            <tr>
                <td>{{ $f['factor'] }}</td>
                <td>{{ $f['valor'] }}</td>
                <td style="text-align: right;">{{ number_format((float) $f['puntaje'], 0, ',', '.') }}</td>
                <td style="text-align: right;">{{ number_format((float) $f['ponderacion'], 0, ',', '.') }}</td>
                <td style="text-align: right;">{{ number_format((float) $f['contribucion'], 4, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin datos de ficha.</td></tr>
        @endforelse
    </tbody>
</table>

@forelse ($periodos as $p)
    <h3 style="margin: 14px 0 4px; font-size: {{ $esExcel ? '12' : '11' }}px;">
        {{ $p['periodo_etiqueta'] }}
        — Inusualidad: {{ $p['inusualidad_nombre'] }} (p={{ number_format((float) $p['inusualidad_puntaje'], 0, ',', '.') }})
        — Riesgo calculado: <strong>{{ $p['riesgo'] }}</strong>
        @if (($p['riesgo_guardado'] ?? '') !== '' && ($p['riesgo_guardado'] ?? '') !== ($p['riesgo'] ?? ''))
            (guardado: {{ $p['riesgo_guardado'] }})
        @endif
    </h3>
    <p style="margin: 0 0 6px;">
        Premios: {{ $p['cantidad_premios'] }}
        — Monto operado: $ {{ number_format((float) $p['monto_operado'], 2, ',', '.') }}
        — Último juego: {{ $p['juego_nombre'] !== '' ? $p['juego_nombre'] : '—' }}
        (p={{ number_format((float) $p['juego_puntaje'], 0, ',', '.') }})
        — Total matriz: {{ number_format((float) $p['total'], 4, ',', '.') }}
    </p>
    <table class="data">
        <thead>
            <tr>
                <th>Factor</th>
                <th style="text-align: right;">Puntaje</th>
                <th style="text-align: right;">Pond. %</th>
                <th style="text-align: right;">Contribución</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($p['lineas'] as $l)
                <tr>
                    <td>{{ $l['factor'] }}</td>
                    <td style="text-align: right;">{{ number_format((float) $l['puntaje'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">{{ number_format((float) $l['ponderacion'], 0, ',', '.') }}</td>
                    <td style="text-align: right;">{{ number_format((float) $l['contribucion'], 4, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>TOTAL</strong></td>
                <td></td>
                <td></td>
                <td style="text-align: right;"><strong>{{ number_format((float) $p['total'], 4, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
@empty
    <p>No hay períodos de riesgo grabados en la ficha.</p>
@endforelse
