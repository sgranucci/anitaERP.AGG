{{--
    Campo depósito: ID oculto + código + descripción + modal consulta.
    Variables: $prefix (salida|entrada), $depositoId, $codigo, $descripcion, $label
--}}
@php
    $prefix = $prefix ?? 'deposito';
    $label = $label ?? 'Depósito';
    $depositoId = $depositoId ?? '';
    $codigo = $codigo ?? '';
    $descripcion = $descripcion ?? '';
@endphp
<div class="form-group col-12 mb-2 tm-deposito-campo" id="tm_deposito_{{ $prefix }}">
    <label class="d-block">{{ $label }}</label>
    <div class="d-flex flex-wrap align-items-center" style="gap: 6px;">
        <input type="hidden" class="deposito_id" id="deposito_{{ $prefix }}_id"
            value="{{ $depositoId }}">
        <button type="button" title="Consulta depósitos" class="btn btn-outline-secondary btn-sm consultadeposito">
            <i class="fa fa-search"></i>
        </button>
        <input type="text" class="form-control codigodeposito flex-grow-0"
            id="deposito_{{ $prefix }}_codigo" value="{{ $codigo }}"
            placeholder="Código" autocomplete="off" style="max-width: 6rem;">
        <input type="text" class="form-control descripciondeposito flex-grow-1"
            id="deposito_{{ $prefix }}_descripcion" value="{{ $descripcion }}"
            placeholder="Descripción" readonly>
    </div>
</div>
