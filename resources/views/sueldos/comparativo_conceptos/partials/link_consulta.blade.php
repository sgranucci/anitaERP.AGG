{{-- Link azul a ABM en solapa sin menú. Variables: $url, $texto, $habilitado --}}
@if (!empty($habilitado) && !empty($url))
    <a href="{{ $url }}" class="text-primary" target="_blank" rel="noopener">{{ $texto }}</a>
@else
    {{ $texto }}
@endif
