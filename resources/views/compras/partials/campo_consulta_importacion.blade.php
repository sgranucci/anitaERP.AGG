{{--
    Carpeta de importación: ID oculto + código + lupa/F1.
    Requiere includes.compras.modalconsulta_importacion_carpeta + compras/importacion/consulta_carpeta.js
--}}
@php
    $prefix = $prefix ?? 'importacion';
    $importacionId = $importacionId ?? '';
    $codigo = $codigo ?? '';
    $estado = $estado ?? '';
    $label = $label ?? 'Carpeta importaci&oacute;n';
    $inputName = $inputName ?? 'importacion_id';
    $inputId = $inputId ?? ('importacion_'.$prefix.'_id');
    $required = ! empty($required);
    $readonly = ! empty($readonly);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('listar-importacion', false) || can('editar-importacion', false);
    $editUrl = ((int) $importacionId > 0 && $puedeAbrirAbm)
        ? route('editar_importacion', ['id' => (int) $importacionId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
    $ayuda = $ayuda ?? 'Opcional. Vincula esta OC a una carpeta de comercio exterior.';
@endphp
<div class="form-group row tm-importacion-campo" data-importacion-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="importacion_id"
                   value="{{ $importacionId }}">
            @if (! $readonly)
                <button type="button" title="Consulta carpetas de importaci&oacute;n (F1)"
                        class="btn-accion-tabla consultaimportacion flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-importacion tooltipsC flex-shrink-0 {{ (int) $importacionId > 0 ? '' : 'd-none' }}"
                   title="Abrir carpeta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoimportacion"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="IMP-…" autocomplete="off"
                   style="width: 9rem; flex-shrink: 0;"
                   {{ $readonly ? 'readonly' : '' }}>
            <input type="text" class="form-control estadoimportacion text-truncate"
                   id="{{ $inputId }}_estado" value="{{ $estado }}"
                   placeholder="Estado" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
        @if ($ayuda !== '')
            <small class="form-text text-muted">{!! $ayuda !!}</small>
        @endif
    </div>
</div>
