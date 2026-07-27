{{--
    Campo consulta maestro SIFAB: guarda codigo_interno_sifab en name=inputName + nombre readonly.
    Params: recurso, label, inputName, codigoInterno, nombre, maestroId, editRoute, col_label, col_input
--}}
@php
    use App\Support\Stock\SifabMaestroConsultaCatalogo;

    $recurso = $recurso ?? '';
    $def = SifabMaestroConsultaCatalogo::def($recurso) ?? [];
    $label = $label ?? ($def['label'] ?? 'Maestro SIFAB');
    $inputName = $inputName ?? ($def['input_name'] ?? $recurso);
    $inputId = $inputId ?? $inputName;
    $codigoInterno = old($inputName, $codigoInterno ?? '');
    $nombre = $nombre ?? '';
    $maestroId = (int) ($maestroId ?? 0);
    $editRoute = $editRoute ?? ($def['edit_route'] ?? null);
    $colLabel = $col_label ?? 'col-lg-4 col-form-label';
    $colInput = $col_input ?? 'col-lg-8';
    $soloLectura = $solo_lectura ?? false;
    $puedeAbrirAbm = $editRoute && SifabMaestroConsultaCatalogo::puedeAbrirAbm($recurso);
    $editUrl = ($maestroId > 0 && $puedeAbrirAbm && $editRoute)
        ? route($editRoute, ['id' => $maestroId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="form-group row mb-2 tm-sifab-maestro-campo" data-recurso="{{ $recurso }}" id="tm_sifab_{{ $inputId }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }}">{!! $label !!}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="sifab-maestro-codigo-interno"
                value="{{ $codigoInterno }}"
                data-maestro-id="{{ $maestroId > 0 ? $maestroId : '' }}">
            @if ($soloLectura)
                <input type="text" class="form-control sifab-maestro-codigo" id="{{ $inputId }}_codigo"
                    value="{{ $codigoInterno }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control sifab-maestro-nombre text-truncate" id="{{ $inputId }}_nombre"
                    value="{{ $nombre }}" readonly style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta (F1)" class="btn-accion-tabla consultasifabmaestro flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-sifab-maestro tooltipsC flex-shrink-0 {{ $maestroId > 0 ? '' : 'd-none' }}"
                        title="Abrir ABM"
                        data-edit-route="{{ $editRoute }}">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control sifab-maestro-codigo" id="{{ $inputId }}_codigo"
                    value="{{ $codigoInterno }}"
                    placeholder="C&oacute;d." title="C&oacute;digo interno SIFAB o c&oacute;digo; Enter valida; F1 consulta"
                    autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control sifab-maestro-nombre text-truncate" id="{{ $inputId }}_nombre"
                    value="{{ $nombre }}" placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
