{{--
    Campo concepto de venta: ID oculto + codigo + descripcion + modal consulta.

    Variables:
    - $conceptoId, $codigo, $descripcion
    - $label, $inputName (default concepto_venta_id), $inputId
    - $required (default false), $solo_lectura
    - $col_label, $col_input
    - $ayuda_tooltip
--}}
@php
    $label = $label ?? 'Concepto de venta';
    $conceptoId = $conceptoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'concepto_venta_id';
    $inputId = $inputId ?? 'concepto_venta_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-3 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-6';
    $puedeAbrirAbm = can('editar-conceptos-venta', false) || can('listar-conceptos-venta', false);
    $editUrl = ((int) $conceptoId > 0 && $puedeAbrirAbm)
        ? route('editar_concepto_venta', ['id' => (int) $conceptoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-concepto-venta-campo">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}@if(!empty($ayuda_tooltip)) <i class="fa fa-question-circle text-muted tooltipsC ml-1" title="{{ $ayuda_tooltip }}"></i>@endif</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="concepto_venta_id"
                value="{{ $conceptoId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigoconceptoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreconceptoventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta conceptos (F1)" class="btn-accion-tabla consultaconceptoventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-concepto-venta tooltipsC flex-shrink-0 {{ (int) $conceptoId > 0 ? '' : 'd-none' }}"
                        title="Abrir concepto en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigoconceptoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombreconceptoventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
