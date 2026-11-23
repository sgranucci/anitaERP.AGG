@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($fallback ?? $key);
    $ordenables = ['id', 'numerocheque', 'fechaemision', 'fechapago', 'monto'];
    $clase = $key === 'monto' ? 'text-right' : ($key === 'moneda' ? 'text-center' : '');
    $clase = trim($clase.' lw-th-drag');
    $titleTh = $titulo.' — arrastrá para mover · × para ocultar (con confirmación)';
    if ($key === 'tipo') {
        $titleTh = 'Físico / e-cheq — arrastrá para mover · × para ocultar (con confirmación)';
    } elseif ($key === 'moneda') {
        $titleTh = 'Moneda — arrastrá para mover · × para ocultar (con confirmación)';
    }
@endphp
<th class="{{ $clase }}" title="{{ $titleTh }}" data-col-key="{{ $key }}" draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    @if (in_array($key, $ordenables, true))
        <a href="{{ $urlOrden($key) }}" class="text-dark lw-sort-link" draggable="false"><span class="lw-th-label">{{ $titulo }}</span>{{ $marcaOrden($key) }}</a>
    @else
        <span class="lw-th-titulo">{{ $titulo }}</span>
    @endif
    <button type="button" class="lw-th-hide" title="Ocultar columna (pide confirmación)" aria-label="Ocultar columna" draggable="false">
        <i class="fa fa-times" aria-hidden="true"></i>
    </button>
</th>
