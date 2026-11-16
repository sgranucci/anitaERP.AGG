{{--
    País: ID oculto + código + nombre + lupa/F1.
    Requiere includes.configuracion.modalconsultapais + configuracion/pais/consulta.js
--}}
@php
    $prefix = $prefix ?? 'pais';
    $paisId = $paisId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Pa&iacute;s';
    $inputName = $inputName ?? 'pais_id';
    $inputId = $inputId ?? ('pais_'.$prefix.'_id');
    $required = ! empty($required);
    $readonly = ! empty($readonly);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-paises', false) || can('listar-paises', false);
    $editUrl = ((int) $paisId > 0 && $puedeAbrirAbm)
        ? route('editar_pais', ['id' => (int) $paisId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-pais-campo" data-pais-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="pais_id" value="{{ $paisId }}"
                   @if ($required) required @endif>
            @if (! $readonly)
                <button type="button" title="Consulta pa&iacute;ses (F1)" class="btn-accion-tabla consultapais flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-pais tooltipsC flex-shrink-0 {{ (int) $paisId > 0 ? '' : 'd-none' }}"
                   title="Abrir pa&iacute;s en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigopais" id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;"
                   {{ $readonly ? 'readonly' : '' }}>
            <input type="text" class="form-control nombrepais text-truncate" id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Nombre" readonly style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
