{{--
    Bien de uso (reemplazo / filtros). Requiere modalconsultabienuso + bien_uso/consulta.js
--}}
@php
    $prefix = $prefix ?? 'bien';
    $bienId = $bienId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $label = $label ?? 'Bien de uso';
    $inputName = $inputName ?? 'bien_uso_id';
    $inputId = $inputId ?? ('bien_uso_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-bien-uso', false) || can('listar-bien-uso', false);
    $editUrl = ((int) $bienId > 0 && $puedeAbrirAbm)
        ? route('editar_bien_uso', ['id' => (int) $bienId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-bien-uso-campo" data-bien-uso-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="bien_uso_id"
                   value="{{ $bienId }}">
            <button type="button" title="Consulta bienes de uso (F1)"
                    class="btn-accion-tabla consultabienuso flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-bien-uso tooltipsC flex-shrink-0 {{ (int) $bienId > 0 ? '' : 'd-none' }}"
                   title="Abrir bien en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigobienuso"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control descripcionbienuso text-truncate"
                   id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
