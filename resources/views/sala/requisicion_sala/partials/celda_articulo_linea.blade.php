@php
    $sku = $sku ?? '';
    $soloLectura = $soloLectura ?? false;
@endphp
<div class="celda-articulo-ms-wrapper">
    <div class="celda-articulo-ms d-flex align-items-center flex-nowrap mb-0">
        @if (! $soloLectura)
            <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0" style="padding:1px 4px;">
                <i class="fa fa-search text-primary"></i>
            </button>
        @endif
        <input type="text"
            class="codigoarticulo form-control form-control-sm flex-grow-1"
            value="{{ $sku }}"
            autocomplete="off"
            placeholder="SKU"
            @if ($soloLectura) readonly @endif>
    </div>
</div>
