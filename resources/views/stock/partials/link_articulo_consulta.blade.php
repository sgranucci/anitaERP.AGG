@php
    $articuloIdLink = (int) ($articuloId ?? 0);
    $textoLink = trim((string) ($texto ?? ''));
    $tituloLink = (string) ($titulo ?? 'Consultar artículo');
    $puedeLink = $articuloIdLink > 0 && $textoLink !== '';
@endphp
@if ($puedeLink)
    <a href="{{ \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar($articuloIdLink) }}"
       class="text-primary"
       target="_blank"
       rel="noopener"
       title="{{ $tituloLink }}">{{ $textoLink }}</a>
@else
    {{ $textoLink }}
@endif
