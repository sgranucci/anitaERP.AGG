@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $alineado = $key === 'monto' ? 'text-right' : '';
@endphp
<th class="{{ $alineado }}">{{ $titulo }}</th>
