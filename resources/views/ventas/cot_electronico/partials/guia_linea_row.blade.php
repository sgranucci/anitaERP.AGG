@php
    $editable = $editable ?? true;
    $l = $linea ?? null;
@endphp
<tr class="fila-cot-guia-linea">
    <td>
        <input type="hidden" name="linea_id[]" value="{{ $l->id ?? '' }}">
        <input type="hidden" name="linea_tipo[]" class="linea-tipo" value="{{ $l->tipo ?? '' }}">
        <input type="hidden" name="linea_letra[]" class="linea-letra" value="{{ $l->letra ?? '' }}">
        <input type="hidden" name="linea_sucursal[]" class="linea-sucursal" value="{{ $l->sucursal ?? '' }}">
        <input type="hidden" name="linea_numero[]" class="linea-numero" value="{{ $l->numero ?? '' }}">
        <input type="hidden" name="linea_venta_id[]" class="linea-venta-id" value="{{ $l->venta_id ?? '' }}">
        <input type="hidden" name="linea_transporte_id[]" class="linea-transporte-id" value="{{ $l->transporte_id ?? '' }}">
        <input type="hidden" name="linea_entrega[]" class="linea-entrega" value="{{ $l->entrega ?? '' }}">
        <div class="input-group input-group-sm">
            <input type="text" class="form-control linea-factura-codigo"
                value="{{ $l ? $l->etiquetaFactura() : '' }}"
                placeholder="83027 o 12-83027"
                title="Número, PV-número o FAC A-12-83027. Enter resuelve."
                {{ $editable ? '' : 'readonly' }}
                autocomplete="off">
            @if ($editable)
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-primary btn-resolver-factura" title="Enter / resolver">
                        <i class="fa fa-check"></i>
                    </button>
                </div>
            @endif
        </div>
        <small class="text-danger linea-error d-none"></small>
    </td>
    <td>
        <input type="hidden" name="linea_cliente_codigo[]" class="linea-cliente-codigo" value="{{ $l->cliente_codigo ?? '' }}">
        <input type="text" name="linea_cliente_nombre[]" class="form-control form-control-sm linea-cliente-nombre" readonly
            value="{{ $l->cliente_nombre ?? '' }}">
    </td>
    <td>
        <input type="text" name="linea_bultos[]" class="form-control form-control-sm text-right linea-bultos"
            value="{{ isset($l) ? number_format((float) $l->bultos, 2, '.', '') : '' }}"
            {{ $editable ? '' : 'readonly' }}>
    </td>
    <td>
        <input type="text" name="linea_cantidad[]" class="form-control form-control-sm text-right linea-cantidad"
            value="{{ isset($l) ? number_format((float) $l->cantidad, 2, '.', '') : '' }}"
            {{ $editable ? '' : 'readonly' }}>
    </td>
    <td>
        <input type="text" name="linea_valor[]" class="form-control form-control-sm text-right linea-valor"
            value="{{ isset($l) ? number_format((float) $l->valor_declarado, 2, '.', '') : '' }}"
            {{ $editable ? '' : 'readonly' }}>
    </td>
    <td>
        <input type="text" name="linea_transporte_codigo[]" class="form-control form-control-sm linea-transporte-codigo" readonly
            value="{{ $l->transporte_codigo ?? '' }}">
    </td>
    <td class="text-center">
        @if ($editable)
            <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-linea" title="Quitar">
                <i class="fa fa-times-circle"></i>
            </button>
        @endif
    </td>
</tr>
