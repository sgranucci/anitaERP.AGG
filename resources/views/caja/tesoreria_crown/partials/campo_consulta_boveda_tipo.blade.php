{{--
    Transacción de bóveda: id oculto + código + nombre + F1/lupa.
--}}
@php
    $tipoId = old('tipo_id', $tipoId ?? '');
    $codigo = old('tipo_codigo', $tipoCodigo ?? '');
    $nombre = old('tipo_nombre', $tipoNombre ?? '');
    $editable = ! empty($editable);
    $required = ! empty($required);
    $colLabel = $col_label ?? 'col-lg-3 control-label text-right pr-2';
    $colInput = $col_input ?? 'col-lg-8';
    $etiqueta = $etiqueta ?? 'Transacción';
@endphp
<div class="form-group row tm-boveda-tipo-campo">
    <label for="boveda_tipo_codigo" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">{{ $etiqueta }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
            <input type="hidden" name="tipo_id" id="boveda_tipo_id" value="{{ $tipoId }}"
                   data-monedas="{{ implode(',', $monedas ?? ['ARS', 'USD', 'EUR']) }}"
                   data-referencia="{{ $referencia ?? '' }}"
                   data-efecto="{{ $efecto ?? '' }}">
            @if ($editable)
                <button type="button" class="btn-accion-tabla tooltipsC consultabovedatipo flex-shrink-0" title="Consultar transacciones (F1)">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" name="tipo_codigo" id="boveda_tipo_codigo" class="form-control codigobovedatipo"
                       value="{{ $codigo }}" placeholder="C&oacute;d." autocomplete="off" inputmode="numeric"
                       title="C&oacute;digo; Enter valida y avanza; F1 o lupa consulta" style="width:5.5rem;flex-shrink:0;"
                       @if ($required) required @endif
                       data-resuelto="{{ $tipoId !== '' && $tipoId !== null ? $codigo : '' }}">
            @else
                <input type="text" id="boveda_tipo_codigo" class="form-control" value="{{ $codigo }}" readonly style="width:5.5rem;flex-shrink:0;">
            @endif
            <input type="text" name="tipo_nombre" id="boveda_tipo_nombre" class="form-control nombrebovedatipo text-truncate"
                   value="{{ $nombre }}" placeholder="Descripci&oacute;n" readonly style="min-width:0;flex:1 1 auto;">
            <span id="boveda_tipo_efecto" class="text-muted small text-nowrap">{{ $efecto ?? '' }}</span>
        </div>
    </div>
</div>
