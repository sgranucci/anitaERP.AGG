{{--
    Incoterm: ID oculto + abreviatura + nombre + lupa/F1.
    Requiere includes.ventas.modalconsultaincoterm + ventas/incoterm/consulta.js
--}}
@php
    $prefix = $prefix ?? 'incoterm';
    $incotermId = $incotermId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $label = $label ?? 'Incoterm';
    $inputName = $inputName ?? 'incoterm_id';
    $inputId = $inputId ?? ('incoterm_'.$prefix.'_id');
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-incoterms', false) || can('listar-incoterms', false);
    $editUrl = ((int) $incotermId > 0 && $puedeAbrirAbm)
        ? route('editar_incoterm', ['id' => (int) $incotermId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row tm-incoterm-campo" data-incoterm-campo="1">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="incoterm_id"
                   value="{{ $incotermId }}">
            <button type="button" title="Consulta Incoterms (F1)"
                    class="btn-accion-tabla consultaincoterm flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-incoterm tooltipsC flex-shrink-0 {{ (int) $incotermId > 0 ? '' : 'd-none' }}"
                   title="Abrir Incoterm en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoincoterm"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="Abr." autocomplete="off"
                   style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombreincoterm text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Nombre" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
</div>
