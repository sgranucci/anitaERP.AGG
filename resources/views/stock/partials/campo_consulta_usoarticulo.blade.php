{{--
    Campo uso de articulo: ID oculto + codigo (id) + nombre + modal consulta.
--}}
@php
    $prefix = $prefix ?? 'usoarticulo';
    $label = $label ?? 'Uso';
    $usoarticuloId = $usoarticuloId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'usoarticulo_id';
    $inputId = $inputId ?? 'usoarticulo_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $siguiente = $siguiente ?? '';
    $codigoName = $codigoName ?? '';
    $ayuda = $ayuda ?? '';
    $puedeAbrirAbm = can('editar-uso-de-articulos', false) || can('listar-uso-de-articulos', false);
    $editUrl = ((int) $usoarticuloId > 0 && $puedeAbrirAbm)
        ? route('editar_usoarticulo', ['id' => (int) $usoarticuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-usoarticulo-campo" id="tm_usoarticulo_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="usoarticulo_id"
                value="{{ $usoarticuloId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigousoarticulo"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreusoarticulo text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta usos (F1)" class="btn-accion-tabla consultausoarticulo flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-usoarticulo tooltipsC flex-shrink-0 {{ (int) $usoarticuloId > 0 ? '' : 'd-none' }}"
                        title="Abrir uso en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigousoarticulo flex-shrink-0"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    @if ($codigoName !== '') name="{{ $codigoName }}" @endif
                    placeholder="ID" title="ID o nombre; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem;"
                    @if ($siguiente !== '') data-siguiente="{{ $siguiente }}" @endif>
                <input type="text" class="form-control nombreusoarticulo text-truncate"
                    id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                    placeholder="Todos" readonly tabindex="-1"
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if ($ayuda !== '')
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
    </div>
</div>
