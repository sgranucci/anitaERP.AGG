@php
    $motivoId = $motivoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Motivo';
    $inputName = $inputName ?? 'motivo_sancion_id';
    $inputId = $inputId ?? 'motivo_sancion_id';
    $required = ! empty($required);
    $titleCodigo = 'Código + Enter para validar; F1 o lupa para buscar';
    $puedeAbrirAbm = can('editar-motivo-sancion-sueldos', false) || can('listar-motivo-sancion-sueldos', false);
    $editUrl = ((int) $motivoId > 0 && $puedeAbrirAbm)
        ? route('editar_motivo_sancion_sueldos', ['id' => (int) $motivoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group mb-0 tm-motivo-sancion-campo" data-motivo-sancion-campo="1">
    @if ($label !== '')
        <label class="small mb-0 d-block" for="{{ $inputId }}_codigo" title="{{ $titleCodigo }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
        <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="motivo_sancion_id"
               value="{{ $motivoId }}" {{ $required ? 'required' : '' }}>
        <button type="button" title="Consulta motivos (F1)" class="btn-accion-tabla consultamotivo_sancion flex-shrink-0">
            <i class="fa fa-search text-primary"></i>
        </button>
        @if ($puedeAbrirAbm)
            <a href="{{ $editUrl }}" target="_blank" rel="noopener"
               class="btn-accion-tabla btn-link-editar-motivo-sancion tooltipsC flex-shrink-0 {{ (int) $motivoId > 0 ? '' : 'd-none' }}"
               title="Abrir motivo en ABM">
                <i class="fa fa-edit"></i>
            </a>
        @endif
        <input type="text" class="form-control form-control-sm codigomotivo_sancion"
               id="{{ $inputId }}_codigo" value="{{ $codigo }}"
               placeholder="Cód." autocomplete="off" title="{{ $titleCodigo }}"
               style="width: 4.5rem; flex-shrink: 0;">
        <input type="text" class="form-control form-control-sm nombremotivo_sancion text-truncate"
               id="{{ $inputId }}_nombre" value="{{ $descripcion }}"
               placeholder="Descripción" readonly
               style="min-width: 0; flex: 1 1 auto;">
    </div>
</div>
