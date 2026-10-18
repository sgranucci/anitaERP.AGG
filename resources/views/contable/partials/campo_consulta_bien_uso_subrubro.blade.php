{{--
    Subrubro de bienes de uso. Requiere modalconsultabienusosubrubro + consulta.js
--}}
@php
    $prefix = $prefix ?? 'subrubro';
    $subrubroId = $subrubroId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Subrubro';
    $inputName = $inputName ?? 'bien_uso_subrubro_id';
    $inputId = $inputId ?? ('bien_uso_subrubro_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-bien-uso-subrubro', false) || can('listar-bien-uso-subrubro', false);
    $editUrl = ((int) $subrubroId > 0 && $puedeAbrirAbm)
        ? route('editar_bien_uso_subrubro', ['id' => (int) $subrubroId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-bien-uso-subrubro-campo" data-bien-uso-subrubro-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="bien_uso_subrubro_id"
                   value="{{ $subrubroId }}">
            <button type="button" title="Consulta subrubros (F1)"
                    class="btn-accion-tabla consultabienusosubrubro flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-bien-uso-subrubro tooltipsC flex-shrink-0 {{ (int) $subrubroId > 0 ? '' : 'd-none' }}"
                   title="Abrir subrubro en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigobienusosubrubro"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombrebienusosubrubro text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Nombre" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
