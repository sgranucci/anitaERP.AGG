@php
    $slugGuia = $slug ?? '';
    $tituloGuia = $titulo ?? 'Guía paso a paso';
    $claseBotonGuia = $clase ?? 'btn btn-light btn-sm mr-1';
@endphp
@if ($slugGuia !== '')
    <a href="{{ route('guia_paso_a_paso', ['slug' => $slugGuia]) }}"
       class="{{ $claseBotonGuia }}"
       target="_blank"
       rel="noopener"
       title="{{ $tituloGuia }}">
        <i class="fa fa-book"></i> Guía
    </a>
@endif
