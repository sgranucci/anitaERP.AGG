{{--
    Campo codigo SENASA: ID oculto + codigo + descripcion + modal consulta (+ enlace ABM).

    Variables:
    - $codigosenasaId, $codigo, $descripcion
    - $label, $inputName (default codigosenasa_id), $inputId
    - $required (default false), $solo_lectura
    - $col_label, $col_input
    - $focusSiguiente (selector CSS)
    - $mostrar_editar (default true)

    Nota: textos visibles con entidades HTML para evitar corrupcion de encoding.
--}}
@php
    $label = $label ?? 'Código SENASA';
    $codigosenasaId = $codigosenasaId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'codigosenasa_id';
    $inputId = $inputId ?? 'codigosenasa_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 col-form-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $focusSiguiente = $focusSiguiente ?? '';
    $puedeAbrirAbm = can('editar-codigo-senasa-stock', false) || can('listar-codigo-senasa-stock', false);
    $editUrl = ((int) $codigosenasaId > 0 && $puedeAbrirAbm)
        ? route('editar_codigosenasa', ['id' => (int) $codigosenasaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-codigosenasa-campo"
    @if ($focusSiguiente !== '')
        data-focus-siguiente="{{ $focusSiguiente }}"
    @endif
>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="codigosenasa_id"
                value="{{ $codigosenasaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigocodigosenasa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncodigosenasa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta c&oacute;digo SENASA (F1)" class="btn-accion-tabla consultacodigosenasa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-codigosenasa tooltipsC flex-shrink-0 {{ (int) $codigosenasaId > 0 ? '' : 'd-none' }}"
                        title="Abrir c&oacute;digo SENASA en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocodigosenasa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcioncodigosenasa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
