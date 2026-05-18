@php
    /** @var object|null $formula */
    $result = $formula->costo_total_result ?? null;
    $tituloIndex = 'Costo total estimado (cant. × FC × últ. compra)';
    if ($result && ! $result->completo) {
        $tituloIndex .= ' — parcial';
    }
@endphp
@if ($result && ($result->total > 0 || ! $result->completo))
<br><span class="d-inline-block text-monospace {{ $result->completo ? 'text-secondary' : 'text-warning' }}" style="font-size: 0.9rem; line-height: 1.3;" title="{{ $tituloIndex }}"><span class="text-muted font-weight-normal">Costo estimado</span> {{ number_format($result->total, 2, ',', '.') }}</span>
@endif
