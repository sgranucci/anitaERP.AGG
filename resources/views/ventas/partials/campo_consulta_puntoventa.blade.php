{{--
    Campo punto de venta: ID oculto + codigo + nombre + modal consulta (+ enlace ABM).
--}}
@php
    $prefix = $prefix ?? 'puntoventa';
    $label = $label ?? 'Punto de venta';
    $puntoventaId = $puntoventaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'puntoventa_id';
    $inputId = $inputId ?? 'puntoventa_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $layout = $layout ?? 'form_row';
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-puntos-de-venta', false) || can('listar-puntos-de-venta', false);
    $editUrl = ((int) $puntoventaId > 0 && $puedeAbrirAbm)
        ? route('editar_puntoventa', ['id' => (int) $puntoventaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

@if ($layout === 'form_row')
<div class="form-group row tm-puntoventa-campo" id="tm_puntoventa_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center tm-puntoventa-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="puntoventa_id"
                value="{{ $puntoventaId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigopuntoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcionpuntoventa text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta puntos de venta (F1)" class="btn-accion-tabla consultapuntoventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-puntoventa tooltipsC flex-shrink-0 {{ (int) $puntoventaId > 0 ? '' : 'd-none' }}"
                        title="Abrir punto de venta en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigopuntoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control descripcionpuntoventa text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
@elseif ($layout === 'inline')
<div class="tm-puntoventa-campo d-flex flex-nowrap align-items-center w-100" style="gap: 4px;" id="tm_puntoventa_{{ $prefix }}">
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="puntoventa_id"
        value="{{ $puntoventaId }}" @if ($required && ! $soloLectura) required @endif>
    @if (! $soloLectura)
        <button type="button" title="Consulta puntos de venta (F1)" class="btn-accion-tabla consultapuntoventa flex-shrink-0">
            <i class="fa fa-search text-primary"></i>
        </button>
        @if ($mostrarEditar && $puedeAbrirAbm)
            <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                class="btn-accion-tabla btn-link-editar-puntoventa tooltipsC flex-shrink-0 {{ (int) $puntoventaId > 0 ? '' : 'd-none' }}"
                title="Abrir punto de venta en ABM">
                <i class="fa fa-edit"></i>
            </a>
        @endif
        <input type="text" class="form-control form-control-sm codigopuntoventa"
            id="{{ $inputId }}_codigo" value="{{ $codigo }}"
            placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
        <input type="text" class="form-control form-control-sm descripcionpuntoventa text-truncate"
            id="{{ $inputId }}_nombre" value="{{ $nombre }}"
            placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
    @else
        <input type="text" class="form-control form-control-sm codigopuntoventa" value="{{ $codigo }}" readonly style="width: 5.5rem;">
        <input type="text" class="form-control form-control-sm descripcionpuntoventa" value="{{ $nombre }}" readonly>
    @endif
</div>
@endif
