@php
    $cbuPagoVal = old('cbu_pago', $cbu_pago ?? ($data->cbu_pago ?? ''));
    $fpIdVal = old('proveedor_formapago_id', $proveedor_formapago_id ?? ($data->proveedor_formapago_id ?? ''));
    $colLabel = $col_label ?? 'col-lg-2 col-form-label text-right';
    $colInput = $col_input ?? 'col-lg-6';
    $requeridoCbu = ! empty($requerido);
@endphp
<div class="form-group row align-items-center tm-cbu-pago-campo" id="div-cbu-pago">
    <label for="cbu_pago_mostrar" class="{{ $colLabel }}{{ $requeridoCbu ? ' requerido' : '' }}">CBU pago</label>
    <div class="{{ $colInput }}">
        <input type="hidden" name="proveedor_formapago_id" id="proveedor_formapago_id" value="{{ $fpIdVal }}">
        <input type="hidden" name="cbu_pago" id="cbu_pago" value="{{ $cbuPagoVal }}">
        <div class="input-group">
            <input type="text" class="form-control text-monospace" id="cbu_pago_mostrar" readonly
                   value="{{ $cbuPagoVal }}" placeholder="Se elige al seleccionar proveedor"
                   title="CBU destino de la transferencia">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-primary consultacbupago" id="btn-consulta-cbu-pago"
                        title="Elegir CBU del proveedor">
                    <i class="fa fa-search"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btn-limpiar-cbu-pago" title="Limpiar CBU">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <small class="text-muted d-block mt-1" id="cbu_pago_etiqueta"></small>
        <small class="text-danger d-none d-block mt-1" id="cbu_pago_aviso"></small>
    </div>
</div>
