{{--
    Campo art&iacute;culo para reportes (rango desde/hasta).
    Usa includes.stock.modalconsultaarticulo + stock/articulo/consulta.js.
--}}
@php
    $prefix = $prefix ?? 'articulo';
    $label = $label ?? 'Art&iacute;culo';
    $articuloId = $articuloId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'articulo_id';
    $inputId = $inputId ?? ('articulo_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-2 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-4';
    $nextFocus = $next_focus ?? null;
    $help = $help ?? 'Vac&iacute;o = todos. F1 o lupa consulta; Enter resuelve por SKU.';
    $puedeConsultar = \App\Support\Stock\ArticuloConsultaDesdeModal::puedeConsultar();
    $editUrl = ((int) $articuloId > 0 && $puedeConsultar)
        ? \App\Support\Stock\ArticuloConsultaDesdeModal::urlEditar((int) $articuloId)
        : '#';
@endphp
<div class="form-group row tm-articulo-campo mb-2" id="tm_articulo_{{ $prefix }}"
    @if ($nextFocus)
        data-next-focus="{{ $nextFocus }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="articulo_id"
                value="{{ $articuloId }}" @if ($required) required @endif>
            <button type="button" title="Consulta art&iacute;culos (F1)" class="btn-accion-tabla consultaarticulo flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeConsultar)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                    class="btn-accion-tabla btn-link-articulo tooltipsC flex-shrink-0 {{ (int) $articuloId > 0 ? '' : 'd-none' }}"
                    title="Consultar art&iacute;culo en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoarticulo" id="{{ $inputId }}_codigo"
                value="{{ $codigo }}" placeholder="SKU" autocomplete="off"
                title="SKU. F1 = consulta, Enter = resolver"
                style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control descripcionarticulo text-truncate" id="{{ $inputId }}_descripcion"
                value="{{ $descripcion }}" placeholder="Descripci&oacute;n" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
        @if ($help)
            <small class="form-text text-muted">{!! $help !!}</small>
        @endif
    </div>
</div>
