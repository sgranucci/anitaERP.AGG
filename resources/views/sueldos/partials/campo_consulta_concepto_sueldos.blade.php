{{--
    Campo concepto de liquidaci&oacute;n (sueldos): ID oculto + c&oacute;digo + descripci&oacute;n + lupa + enlace ABM.
    layouts: form_row (ABM) | compact (forms inline / planes cuota)
    La ayuda F1/Enter va en title (no debajo del input) para no desalinearlo.
--}}
@php
    $conceptoId = $conceptoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Concepto';
    $inputName = $inputName ?? 'concepto_id';
    $inputId = $inputId ?? 'concepto_sueldos_id';
    $layout = $layout ?? 'form_row';
    $required = ! empty($required);
    $titleCodigo = 'Código + Enter para validar; F1 o lupa para buscar';
    $colLabel = $col_label ?? 'col-lg-4';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-concepto-sueldos', false) || can('listar-concepto-sueldos', false);
    $editUrl = ((int) $conceptoId > 0 && $puedeAbrirAbm)
        ? route('editar_concepto_sueldos', [
            'id' => (int) $conceptoId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
    $codigoFmt = $codigo !== '' && $codigo !== null
        ? str_pad((string) $codigo, 4, '0', STR_PAD_LEFT)
        : '';
@endphp

@if ($layout === 'compact')
    <div class="form-group mb-0 tm-concepto-sueldos-campo" data-concepto-sueldos-campo="1">
        @if ($label !== '')
            <label class="small mb-0 d-block text-truncate" for="{{ $inputId }}_codigo" title="{{ $label }} — {{ $titleCodigo }}">
                {{ $label }}
                @if ($required)
                    <span class="text-danger">*</span>
                @endif
            </label>
        @endif
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="concepto_sueldos_id"
                   value="{{ $conceptoId }}" {{ $required ? 'required' : '' }}>
            <button type="button" title="Consulta conceptos (F1)"
                    class="btn-accion-tabla consultaconcepto_sueldos flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-concepto-sueldos tooltipsC flex-shrink-0 {{ (int) $conceptoId > 0 ? '' : 'd-none' }}"
                   title="Abrir concepto en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control form-control-sm codigoconcepto_sueldos"
                   id="{{ $inputId }}_codigo" value="{{ $codigoFmt }}"
                   placeholder="C&oacute;d." autocomplete="off" title="{{ $titleCodigo }}"
                   style="width: 4.5rem; flex-shrink: 0;">
            <input type="text" class="form-control form-control-sm nombreconcepto_sueldos text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
@else
    <div class="form-group row tm-concepto-sueldos-campo" data-concepto-sueldos-campo="1">
        <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} col-form-label" title="{{ $titleCodigo }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
        <div class="{{ $colInput }}">
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="concepto_sueldos_id"
                       value="{{ $conceptoId }}" {{ $required ? 'required' : '' }}>
                <button type="button" title="Consulta conceptos (F1)"
                        class="btn-accion-tabla consultaconcepto_sueldos flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                       class="btn-accion-tabla btn-link-editar-concepto-sueldos tooltipsC flex-shrink-0 {{ (int) $conceptoId > 0 ? '' : 'd-none' }}"
                       title="Abrir concepto en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigoconcepto_sueldos"
                       id="{{ $inputId }}_codigo" value="{{ $codigoFmt }}"
                       placeholder="C&oacute;d." autocomplete="off" title="{{ $titleCodigo }}"
                       style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreconcepto_sueldos text-truncate"
                       id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                       placeholder="Descripci&oacute;n" readonly
                       style="min-width: 0; flex: 1 1 auto;">
            </div>
        </div>
    </div>
@endif
