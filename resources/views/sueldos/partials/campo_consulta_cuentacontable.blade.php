{{--
    Campo cuenta contable (ID oculto + c&oacute;digo + descripci&oacute;n + lupa).
    Requiere includes.contable.modalconsultacuentacontable + cuentacontable/consulta.js
--}}
@php
    $cuentaId = $cuentaId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Cuenta';
    $inputName = $inputName ?? 'cuentacontable_id';
    $inputId = $inputId ?? 'cuentacontable_id';
    $required = ! empty($required);
    $titleCodigo = 'C&oacute;digo + Enter para validar; F1 o lupa para buscar';
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-cuentas-contables', false) || can('listar-cuentas-contables', false);
    $editUrl = ((int) $cuentaId > 0 && $puedeAbrirAbm)
        ? route('editar_cuentacontable', [
            'id' => (int) $cuentaId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
@endphp
<div class="form-group row tm-cuentacontable-campo" data-cuentacontable-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}" title="{{ $titleCodigo }}">
        {{ $label }}
        @if ($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="cuentacontable_id"
                   value="{{ $cuentaId }}">
            <input type="hidden" class="cuentacontable_id_previa" value="{{ $cuentaId }}">
            <input type="hidden" class="codigo_previo" value="{{ $codigo }}">
            <button type="button" title="Consulta cuentas (F1)"
                    class="btn-accion-tabla consultacuentacontable flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ (int) $cuentaId > 0 ? '' : 'd-none' }}"
                   title="Abrir cuenta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigocuentacontable"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off" title="{{ $titleCodigo }}"
                   style="width: 7.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombrecuentacontable text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
