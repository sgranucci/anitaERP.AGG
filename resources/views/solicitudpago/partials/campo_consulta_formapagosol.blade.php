{{--
    Forma de pago solicitud: ID oculto + c&oacute;digo + nombre + modal lista (datos en p&aacute;gina).
--}}
@php
    $formapagosolId = $formapagosolId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Forma de pago';
    $inputName = $inputName ?? 'formapagosol_id';
    $inputId = $inputId ?? 'formapagosol_id';
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-6';
@endphp
<div class="form-group row tm-formapagosol-campo" data-formapagosol-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="formapagosol_id"
                   value="{{ $formapagosolId }}" @if ($required) required @endif>
            <button type="button" title="Consulta formas de pago (F1)"
                    class="btn-accion-tabla consulta-formapagosol flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="form-control codigo-formapagosol"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombre-formapagosol text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
