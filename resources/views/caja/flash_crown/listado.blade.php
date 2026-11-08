<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #17202A; }
        h1 { font-size: 14px; margin: 0 0 6px; }
        .meta { margin-bottom: 8px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $titulo ?? 'Flash Crown' }}</h1>
    <div class="meta">Generado {{ now()->format('d/m/Y H:i') }} — {{ count($filas) }} sala(s)</div>
    <table class="data">
        <thead>
            <tr>
                <th>Sala</th>
                <th>Att</th>
                <th>Drop</th>
                <th>Win mesas</th>
                <th>Coin in</th>
                <th>Win slots</th>
                <th>Gaming</th>
                <th>F&amp;B</th>
                <th>Hotel</th>
                <th>Combined</th>
                <th>Val.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                @php
                    $m = \App\Support\Caja\Flash\FlashCrownCalculoSupport::metricasDerivadas($fila->toArray());
                @endphp
                <tr>
                    <td>{{ $fila->sala->codigo ?? '' }} {{ $fila->sala->nombre ?? '' }}</td>
                    <td class="num">{{ number_format((int) $fila->att, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila->cashdrop, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $m['win_mesas'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila->coin_in, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila->slot_r, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $m['gaming'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila->ayb, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $fila->hotel, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $m['combined'], 2, ',', '.') }}</td>
                    <td>{{ $fila->estaValidado() ? 'Sí' : 'No' }}</td>
                </tr>
            @endforeach
            @if (! empty($consolidado))
                @php $cm = \App\Support\Caja\Flash\FlashCrownCalculoSupport::metricasDerivadas($consolidado); @endphp
                <tr>
                    <td><strong>CONSOLIDADO</strong></td>
                    <td class="num"><strong>{{ number_format((int) ($consolidado['att'] ?? 0), 0, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($consolidado['cashdrop'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($cm['win_mesas'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($consolidado['coin_in'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($consolidado['slot_r'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($cm['gaming'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($consolidado['ayb'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($consolidado['hotel'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td class="num"><strong>{{ number_format((float) ($cm['combined'] ?? 0), 2, ',', '.') }}</strong></td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
