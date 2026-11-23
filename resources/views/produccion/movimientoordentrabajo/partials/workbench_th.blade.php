@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $claseCol = 'lw-th-drag';
    $titleTh = $titulo.' — arrastrá para mover · × para ocultar (con confirmación)';
@endphp
<th class="{{ $claseCol }}" title="{{ $titleTh }}" data-col-key="{{ $key }}" draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    <span class="lw-th-titulo">{{ $titulo }}</span>
    <button type="button" class="lw-th-hide" title="Ocultar columna (pide confirmación)" aria-label="Ocultar columna" draggable="false">
        <i class="fa fa-times" aria-hidden="true"></i>
    </button>
</th>
