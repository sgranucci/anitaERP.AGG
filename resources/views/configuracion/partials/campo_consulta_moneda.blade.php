{{--
    Campo moneda: ID oculto + código editable + nombre readonly + modal (F1 / lupa).
--}}
@php
    $prefix = $prefix ?? 'moneda';
    $label = $label ?? 'Moneda';
    $monedaId = (int) ($monedaId ?? 0);
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'moneda_id';
    $inputId = $inputId ?? 'moneda_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 col-form-label control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-monedas', false) || can('listar-monedas', false);
    $editUrl = ($monedaId > 0 && $puedeAbrirAbm)
        ? route('editar_moneda', ['id' => $monedaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row mb-2 tm-moneda-campo" id="tm_moneda_{{ $prefix }}">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="moneda_id"
                value="{{ $monedaId > 0 ? $monedaId : '' }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigomoneda"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreMoneda text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta monedas (F1)" class="btn-accion-tabla consultamoneda flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-moneda tooltipsC flex-shrink-0 {{ $monedaId > 0 ? '' : 'd-none' }}"
                        title="Abrir moneda en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigomoneda"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off"
                    style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreMoneda text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
