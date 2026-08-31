{{--
    Campo abono/contrato de venta: ID oculto + codigo + descripcion + modal consulta.

    Variables:
    - $contratoId, $codigo, $descripcion
    - $label, $inputName (default contrato_venta_id), $inputId
    - $required (default false), $solo_lectura
    - $col_label, $col_input
--}}
@php
    $label = $label ?? 'Abono / contrato';
    $contratoId = $contratoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
    $inputName = $inputName ?? 'contrato_venta_id';
    $inputId = $inputId ?? 'contrato_venta_id';
    $soloLectura = $solo_lectura ?? false;
    $required = $required ?? false;
    $mostrarEditar = $mostrar_editar ?? true;
    $colLabel = $col_label ?? 'col-lg-3 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-6';
    $puedeAbrirAbm = can('editar-contratos-venta', false) || can('listar-contratos-venta', false);
    $editUrl = ((int) $contratoId > 0 && $puedeAbrirAbm)
        ? route('editar_contrato_venta', ['id' => (int) $contratoId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp

<div class="form-group row tm-contrato-venta-campo">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} {{ $required ? 'requerido' : '' }}">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="contrato_venta_id"
                value="{{ $contratoId }}" @if ($required && ! $soloLectura) required @endif>
            @if ($soloLectura)
                <input type="text" class="form-control codigocontratoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}" readonly style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombrecontratoventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @else
                <button type="button" title="Consulta abonos (F1)" class="btn-accion-tabla consultacontratoventa flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                @if ($mostrarEditar && $puedeAbrirAbm)
                    <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                        class="btn-accion-tabla btn-link-editar-contrato-venta tooltipsC flex-shrink-0 {{ (int) $contratoId > 0 ? '' : 'd-none' }}"
                        title="Abrir abono en ABM">
                        <i class="fa fa-edit"></i>
                    </a>
                @endif
                <input type="text" class="form-control codigocontratoventa"
                    id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                    placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
                <input type="text" class="form-control nombrecontratoventa text-truncate"
                    id="{{ $inputId }}_descripcion" value="{{ $descripcion }}"
                    placeholder="Descripci&oacute;n" readonly
                    style="min-width: 0; flex: 1 1 auto;">
            @endif
        </div>
    </div>
</div>
