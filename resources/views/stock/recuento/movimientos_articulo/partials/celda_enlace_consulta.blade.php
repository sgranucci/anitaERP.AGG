@php
    $texto = $texto ?? '—';
    $url = $url ?? null;
    $mostrarEnlaces = $mostrar_enlaces ?? true;
@endphp
@if ($mostrarEnlaces && ! empty($url) && $texto !== '' && $texto !== '—')
    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-primary">{{ $texto }}</a>
@else
    {{ $texto !== '' ? $texto : '—' }}
@endif
