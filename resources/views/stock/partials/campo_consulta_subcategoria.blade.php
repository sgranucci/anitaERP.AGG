{{--
    Campo subcategoria de articulo: ID oculto + codigo + nombre + modal consulta.
--}}
@php
    $prefix = $prefix ?? 'subcategoria';
    $label = $label ?? 'Subcategoría';
    $subcategoriaId = $subcategoriaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'subcategoria_id';
    $inputId = $inputId ?? 'subcategoria_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $siguiente = $siguiente ?? '';
    $codigoName = $codigoName ?? '';
    $ayuda = $ayuda ?? '';
    $filtraCategoria = $filtra_categoria ?? '';
    $puedeAbrirAbm = can('editar-subcategorias', false) || can('listar-subcategorias', false);
    $editUrl = ((int) $subcategoriaId > 0 && $puedeAbrirAbm)
        ? route('editar_subcategoria', ['id' => (int) $subcategoriaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-subcategoria-campo" id="tm_subcategoria_{{ $prefix }}"
    @if ($filtraCategoria !== '') data-filtra-categoria="{{ $filtraCategoria }}" @endif>
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="subcategoria_id"
                value="{{ $subcategoriaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigosubcategoria"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombressubcategoria text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta subcategor&iacute;as (F1)" class="btn-accion-tabla consultasubcategoria flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-subcategoria tooltipsC flex-shrink-0 {{ (int) $subcategoriaId > 0 ? '' : 'd-none' }}"
                        title="Abrir subcategor&iacute;a en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigosubcategoria flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    @if ($codigoName !== '') name="{{ $codigoName }}" @endif
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem;"
                    @if ($siguiente !== '') data-siguiente="{{ $siguiente }}" @endif>
                <input type="text" class="form-control nombressubcategoria text-truncate"
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
