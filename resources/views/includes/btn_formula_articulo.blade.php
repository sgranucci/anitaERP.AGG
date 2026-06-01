@php
    $articuloIdBtn = (int) ($articuloId ?? 0);
@endphp
@if ($articuloIdBtn > 0)
    <button type="button"
            class="btn-accion-tabla tooltipsC js-ver-formula-articulo"
            data-articulo-id="{{ $articuloIdBtn }}"
            title="{{ $title ?? 'Ver fórmula del artículo' }}">
        <i class="fa fa-flask text-info"></i>
    </button>
@endif
