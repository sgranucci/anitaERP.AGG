@php
    $liquidacionId = $liquidacionId ?? '';
    $numero = $numero ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Liquidaci&oacute;n';
    $inputName = $inputName ?? 'liquidacion_id';
    $inputId = $inputId ?? 'liquidacion_sueldos_id';
    $required = ! empty($required);
    $titleNumero = 'N&uacute;mero + Enter para validar; F1 o lupa para buscar';
@endphp

<div class="form-group mb-0 tm-liquidacion-sueldos-campo" data-liquidacion-sueldos-campo="1">
    @if ($label !== '')
        <label class="d-block" for="{{ $inputId }}_numero" title="{!! $titleNumero !!}">
            {!! $label !!}@if($required)<span class="text-danger">*</span>@endif
        </label>
    @endif
    <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}"
               class="liquidacion_sueldos_id" value="{{ $liquidacionId }}"
               {{ $required ? 'required' : '' }}>
        <button type="button" class="btn-accion-tabla consultaliquidacion_sueldos flex-shrink-0"
                title="Consultar liquidaciones (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" class="form-control form-control-sm numeroliquidacion_sueldos"
               id="{{ $inputId }}_numero" value="{{ $numero }}"
               placeholder="N&deg;" autocomplete="off" title="{!! $titleNumero !!}"
               style="width:6.5rem;flex-shrink:0;">
        <input type="text" class="form-control form-control-sm nombreliquidacion_sueldos text-truncate"
               id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
               placeholder="Per&iacute;odo y descripci&oacute;n" readonly
               style="min-width:0;flex:1 1 auto;">
    </div>
</div>



