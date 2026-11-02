@if (! empty($cambiosDia))
    <span class="small mr-1" style="opacity:.85;white-space:nowrap;" title="Cotización de venta de la jornada">
        @foreach ($cambiosDia as $cambio)
            @if (! $loop->first)
                ·
            @endif
            {{ $cambio['codigo'] }}
            @if ($cambio['tasa'] > 0)
                @php
                    $decimalesCambio = abs($cambio['tasa'] - round($cambio['tasa'], 2)) < 0.0000001 ? 2 : 4;
                @endphp
                {{ number_format($cambio['tasa'], $decimalesCambio, ',', '.') }}
                @if (! $cambio['exacta'] && $cambio['fecha'])
                    ({{ \Carbon\Carbon::parse($cambio['fecha'])->format('d/m') }})
                @endif
            @else
                sin cambio
            @endif
        @endforeach
    </span>
@endif
