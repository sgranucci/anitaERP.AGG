{{--
    Campo linea de articulo: ID oculto + codigo + nombre + modal consulta.
--}}
@php
    $prefix = $prefix ?? 'linea';
    $label = $label ?? 'Línea';
    $lineaId = $lineaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'linea_id';
    $inputId = $inputId ?? 'linea_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $siguiente = $siguiente ?? '';
    $ayuda = $ayuda ?? '';
    $puedeAbrirAbm = can('editar-lineas', false) || can('listar-lineas', false);
    $editUrl = ((int) $lineaId > 0 && $puedeAbrirAbm)
        ? route('editar_linea', ['id' => (int) $lineaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-linea-campo" id="tm_linea_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="linea_id"
                value="{{ $lineaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigolinea"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombrelinea text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta l&iacute;neas (F1)" class="btn-accion-tabla consultalinea flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-linea tooltipsC flex-shrink-0 {{ (int) $lineaId > 0 ? '' : 'd-none' }}"
                        title="Abrir l&iacute;nea en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigolinea flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem;"
                    @if ($siguiente !== '') data-siguiente="{{ $siguiente }}" @endif>
                <input type="text" class="form-control nombrelinea text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Todas" readonly tabindex="-1"
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if ($ayuda !== '')
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
    </div>
</div>
