@php
    $enlacePartes = \App\Support\Listado\ListadoEnlaceSupport::partes(
        $filaEnlace,
        $metaEnlace ?? [],
        (string) ($textoEnlace ?? '')
    );
@endphp
@if ($enlacePartes === null)
    {{ $textoEnlace ?? '' }}
@else
    @foreach ($enlacePartes as $iEnlace => $parteEnlace)
        @if ($iEnlace > 0)
            <span class="text-muted"> | </span>
        @endif
        @if ($parteEnlace['url'])
            <a href="{{ $parteEnlace['url'] }}" class="text-primary" target="_blank" rel="noopener" title="Abrir">{{ $parteEnlace['texto'] }}</a>
        @else
            {{ $parteEnlace['texto'] }}
        @endif
    @endforeach
@endif
