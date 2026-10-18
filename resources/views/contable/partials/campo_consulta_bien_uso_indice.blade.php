{{--
    &Iacute;ndice de actualizaci&oacute;n. Requiere modalconsultabienusoindice + consulta.js
--}}
@php
    $prefix = $prefix ?? 'indice';
    $indiceId = $indiceId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Índice';
    $inputName = $inputName ?? 'bien_uso_indice_id';
    $inputId = $inputId ?? ('bien_uso_indice_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-bien-uso-indice', false) || can('listar-bien-uso-indice', false);
    $editUrl = ((int) $indiceId > 0 && $puedeAbrirAbm)
        ? route('editar_bien_uso_indice', ['id' => (int) $indiceId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-bien-uso-indice-campo" data-bien-uso-indice-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="bien_uso_indice_id"
                   value="{{ $indiceId }}">
            <button type="button" title="Consulta índices (F1)"
                    class="btn-accion-tabla consultabienusoindice flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-bien-uso-indice tooltipsC flex-shrink-0 {{ (int) $indiceId > 0 ? '' : 'd-none' }}"
                   title="Abrir índice en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigobienusoindice"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombrebienusoindice text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Nombre" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
