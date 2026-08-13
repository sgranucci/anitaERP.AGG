{{--
    Campo tipo comprobante compras: ID oculto + abreviatura + nombre + modal (F1 / Enter).
--}}
@php
    $prefix = $prefix ?? 'comprobante_proveedor';
    $label = $label ?? 'Tipo comprobante';
    $tipoId = (int) ($tipoId ?? 0);
    $abreviatura = $abreviatura ?? '';
    $nombre = $nombre ?? '';
    $inputName = $inputName ?? 'tipotransaccion_compra_id';
    $inputId = $inputId ?? 'tipotransaccion_compra_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? true;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-4 col-form-label control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $centrocostoId = (int) ($centrocosto_id ?? 0);
    $puedeAbrirAbm = can('editar-tipo-transaccion-compra', false) || can('listar-tipo-transaccion-compra', false);
    $editUrl = ($tipoId > 0 && $puedeAbrirAbm)
        ? route('editar_tipotransaccion_compra', ['id' => $tipoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row mb-2 tm-tipotransaccion-compra-campo" id="tm_tipotransaccion_{{ $prefix }}"
     data-centrocosto-id="{{ $centrocostoId > 0 ? $centrocostoId : '' }}">
    <label for="{{ $inputId }}_abreviatura" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center tm-tipotransaccion-compra-campo-inputs w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="tipotransaccion_compra_id"
                value="{{ $tipoId > 0 ? $tipoId : '' }}"
                @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control abreviaturatipotransaccioncompra"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccioncompra text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta tipos de comprobante (F1)" class="btn-accion-tabla consultatipotransaccioncompra flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-tipotransaccion-compra tooltipsC flex-shrink-0 {{ $tipoId > 0 ? '' : 'd-none' }}"
                        title="Abrir tipo de comprobante en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control abreviaturatipotransaccioncompra"
                    id="{{ $inputId }}_abreviatura" value="{{ $abreviatura }}"
                    placeholder="Abrev." title="Abreviatura; Enter valida; F1 consulta" autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombretipotransaccioncompra text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $nombre }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
        @if ($centrocostoId > 0)
            <small class="form-text text-muted mt-0">Filtrado por centro de costo de la OC.</small>
        @endif
    </div>
</div>
