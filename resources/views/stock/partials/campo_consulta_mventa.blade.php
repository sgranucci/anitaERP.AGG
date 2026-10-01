{{--
    Campo marca de venta: ID oculto + codigo + nombre + modal consulta.
--}}
@php
    $prefix = $prefix ?? 'mventa';
    $label = $label ?? 'Marca';
    $mventaId = $mventaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'mventa_id';
    $inputId = $inputId ?? 'mventa_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $siguiente = $siguiente ?? '';
    $puedeAbrirAbm = can('editar-marcas-de-venta', false) || can('listar-marcas-de-venta', false);
    $editUrl = ((int) $mventaId > 0 && $puedeAbrirAbm)
        ? route('editar_mventa', ['id' => (int) $mventaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-mventa-campo" id="tm_mventa_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="mventa_id"
                value="{{ $mventaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigomventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombremventa text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta marcas (F1)" class="btn-accion-tabla consultamventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-mventa tooltipsC flex-shrink-0 {{ (int) $mventaId > 0 ? '' : 'd-none' }}"
                        title="Abrir marca en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigomventa flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem;"
                    @if ($siguiente !== '') data-siguiente="{{ $siguiente }}" @endif>
                <input type="text" class="form-control nombremventa text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Todas" readonly tabindex="-1"
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
