{{--
    Campo banco: ID oculto + c&oacute;digo + nombre + lupa + enlace ABM.
    layouts: form_row (ABM) | compact (grillas / inline)
    C&oacute;digo + Enter valida; F1 o lupa abre modal.
--}}
@php
    $bancoId = $bancoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Banco';
    $inputName = $inputName ?? 'banco_id';
    $inputId = $inputId ?? 'banco_id';
    $layout = $layout ?? 'form_row';
    $required = ! empty($required);
    $titleCodigo = 'Código + Enter para validar; F1 o lupa para buscar';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-banco', false) || can('listar-banco', false);
    $editUrl = ((int) $bancoId > 0 && $puedeAbrirAbm)
        ? route('editar_banco', [
            'id' => (int) $bancoId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
@endphp

@if ($layout === 'compact')
    <div class="form-group mb-0 tm-banco-campo" data-banco-campo="1">
        @if ($label !== '')
            <label class="small mb-0 d-block text-truncate" for="{{ $inputId }}_codigo" title="{{ $label }} — {{ $titleCodigo }}">
                {{ $label }}
                @if ($required)
                    <span class="text-danger">*</span>
                @endif
            </label>
        @endif
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="banco_id"
                   value="{{ $bancoId }}" {{ $required ? 'required' : '' }}>
            <button type="button" title="Consulta bancos (F1)"
                    class="btn-accion-tabla consultabanco flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-banco tooltipsC flex-shrink-0 {{ (int) $bancoId > 0 ? '' : 'd-none' }}"
                   title="Abrir banco en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control form-control-sm codigobanco"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off" title="{{ $titleCodigo }}"
                   style="width: 4.5rem; flex-shrink: 0;">
            <input type="text" class="form-control form-control-sm nombrebanco text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
@else
    <div class="form-group row tm-banco-campo" data-banco-campo="1">
        <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} col-form-label text-right pr-2" title="{{ $titleCodigo }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
        <div class="{{ $colInput }}">
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="banco_id"
                       value="{{ $bancoId }}" {{ $required ? 'required' : '' }}>
                <button type="button" title="Consulta bancos (F1)"
                        class="btn-accion-tabla consultabanco flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                       class="btn-accion-tabla btn-link-editar-banco tooltipsC flex-shrink-0 {{ (int) $bancoId > 0 ? '' : 'd-none' }}"
                       title="Abrir banco en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigobanco"
                       id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                       placeholder="C&oacute;d." autocomplete="off" title="{{ $titleCodigo }}"
                       style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombrebanco text-truncate"
                       id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                       placeholder="Descripci&oacute;n" readonly
                       style="min-width: 0; flex: 1 1 auto;">
            </div>
        </div>
    </div>
@endif
