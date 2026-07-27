{{--
    Campo concepto solicitud de pago: ID oculto + codigo + nombre + modal consulta (+ enlace ABM).
    Placeholders con entidades HTML para evitar corrupcion de encoding.
--}}
@php
    $conceptoId = $conceptoId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $formaPago = $formaPago ?? '';
    $label = $label ?? 'Concepto';
    $inputName = $inputName ?? 'concepto_solicitudpago_id';
    $inputId = $inputId ?? 'concepto_solicitudpago_id';
    $colLabel = $col_label ?? 'col-lg-4';
    $colInput = $col_input ?? 'col-lg-8';
    $puedeAbrirAbm = can('editar-concepto-solicitud-pago', false) || can('listar-concepto-solicitud-pago', false);
    $editUrl = ((int) $conceptoId > 0 && $puedeAbrirAbm)
        ? route('editar_concepto_solicitudpago', [
            'id' => (int) $conceptoId,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ])
        : '#';
@endphp
<div class="form-group row sp-concepto-campo mb-2" id="sp_concepto_campo">
    <label for="{{ $inputId }}_codigo" class="{{ $colLabel }} col-form-label">{{ $label }}</label>
    <div class="{{ $colInput }}">
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="concepto_solicitudpago_id"
                   value="{{ $conceptoId }}">
            <input type="hidden" name="concepto_forma_pago" id="concepto_forma_pago" value="{{ $formaPago }}">
            <button type="button" title="Consulta conceptos (F1)"
                    class="btn-accion-tabla consultaconcepto_solicitudpago flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbm)
                <a href="{{ $editUrl }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-concepto-solicitudpago tooltipsC flex-shrink-0 {{ (int) $conceptoId > 0 ? '' : 'd-none' }}"
                   title="Abrir concepto en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="form-control codigoconcepto_solicitudpago"
                   id="{{ $inputId }}_codigo" value="{{ $codigo }}"
                   placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;">
            <input type="text" class="form-control nombreconcepto_solicitudpago text-truncate"
                   id="{{ $inputId }}_nombre" value="{{ $nombre }}"
                   placeholder="Descripci&oacute;n" readonly
                   style="min-width: 0; flex: 1 1 auto;">
        </div>
        <small class="form-text text-muted">Ingrese c&oacute;digo y Enter para validar, o F1 / lupa para consultar. Si hay sector, filtr&aacute; primero por sector.</small>
    </div>
</div>
