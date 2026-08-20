{{--
    Campo camion: ID oculto + codigo + descripcion + modal consulta (+ enlace ABM).

    Variables:
    - $camionId, $codigo, $descripcion
    - $label, $inputName (default camion_id), $inputId
    - $required (default true), $solo_lectura
    - $col_label, $col_input
    - $focusSiguiente (selector CSS, ej. #temperatura)
    - $mostrar_editar (default true)

    Nota: textos visibles con entidades HTML para evitar corrupcion de encoding.
--}}
@php
    $label = $label ?? 'Camión';
    $camionId = $camionId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'camion_id';
    $inputId = $inputId ?? 'camion_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-3 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-6';
    $focusSiguiente = $focusSiguiente ?? '';
    $puedeAbrirAbm = can('editar-camion', false) || can('listar-camion', false);
    $editUrl = ((int) $camionId > 0 && $puedeAbrirAbm)
        ? route('editar_camion', ['id' => (int) $camionId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-camion-campo"
    @if ($focusSiguiente !== '')
        data-focus-siguiente="{{ $focusSiguiente }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="camion_id"
                value="{{ $camionId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigocamion"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncamion text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta cami&oacute;n (F1)" class="btn-accion-tabla consultacamion flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-camion tooltipsC flex-shrink-0 {{ (int) $camionId > 0 ? '' : 'd-none' }}"
                        title="Abrir cami&oacute;n en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocamion"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncamion text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Dominio / habilitaci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
