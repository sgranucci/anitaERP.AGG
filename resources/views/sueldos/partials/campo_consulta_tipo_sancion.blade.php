@php
    $tipoId = $tipoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Tipo';
    $inputName = $inputName ?? 'tipo_sancion_id';
    $inputId = $inputId ?? 'tipo_sancion_id';
    $required = ! empty($required);
    $titleCodigo = 'Código + Enter para validar; F1 o lupa para buscar';
    $puedeAbrirAbm = can('editar-tipo-sancion-sueldos', false) || can('listar-tipo-sancion-sueldos', false);
    $editUrl = ((int) $tipoId > 0 && $puedeAbrirAbm)
        ? route('editar_tipo_sancion_sueldos', ['id' => (int) $tipoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group mb-0 tm-tipo-sancion-campo" data-tipo-sancion-campo="1">
    @if ($label !== '')
        <label class="small mb-0 d-block" for="{{ $inputId }}_codigo" title="{{ $titleCodigo }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="tipo_sancion_id"
               value="{{ $tipoId }}" {{ $required ? 'required' : '' }}>
        <button type="button" title="Consulta tipos (F1)" class="btn-accion-tabla consultatipo_sancion flex-shrink-0">
            <i class="fa fa-search text-primary"></i>
        </button>
        @if ($puedeAbrirAbm)
            <a href="{{ $editUrl }}" target="_blank" rel="noopener"
               class="btn-accion-tabla btn-link-editar-tipo-sancion tooltipsC flex-shrink-0 {{ (int) $tipoId > 0 ? '' : 'd-none' }}"
               title="Abrir tipo en ABM">
                <i class="fa fa-edit"></i>
            </a>
        @endif
        <input type="text" class="form-control form-control-sm codigotipo_sancion"
               id="{{ $inputId }}_codigo" value="{{ $codigo }}"
               placeholder="Cód." autocomplete="off" title="{{ $titleCodigo }}"
               style="width: 4.5rem; flex-shrink: 0;">
        <input type="text" class="form-control form-control-sm nombretipo_sancion text-truncate"
               id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
               placeholder="Descripción" readonly
               style="min-width: 0; flex: 1 1 auto;">
    </div>
</div>
