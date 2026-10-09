@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $alineacion = ($key === 'total' || $key === 'cotizacion') ? 'text-right' : '';
@endphp
<th class="{{ $alineacion }}">{{ $titulo }}</th>
