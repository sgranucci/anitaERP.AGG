@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($fallback ?? $key);
    $ordenables = ['id', 'numerocheque', 'fechaemision', 'fechapago', 'monto'];
    $clase = $key === 'monto' ? 'text-right' : ($key === 'moneda' ? 'text-center' : '');
@endphp
<th class="{{ $clase }}" @if ($key === 'tipo') title="Físico / e-cheq" @endif @if ($key === 'moneda') title="Moneda" @endif>
    @if (in_array($key, $ordenables, true))
        <a href="{{ $urlOrden($key) }}" class="text-dark">{{ $titulo }}{{ $marcaOrden($key) }}</a>
    @else
        {{ $titulo }}
    @endif
</th>
