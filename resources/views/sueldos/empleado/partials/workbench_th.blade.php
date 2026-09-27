@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($key);
@endphp
<th>{{ $titulo }}</th>
