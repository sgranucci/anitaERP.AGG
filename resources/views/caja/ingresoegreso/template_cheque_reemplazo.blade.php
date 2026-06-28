<template id="template-renglon-cheque-reemplazo">
    <tr class="item-cheque-reemplazo">
        <td>
            <input type="hidden" name="cheque_anulado_ids[]" class="cheque_anulado_id" value="">
            <input type="text" class="form-control numerocheque_anulado_buscar" placeholder="Nro. a anular" style="width:100px;display:inline-block">
            <button type="button" class="btn btn-sm btn-info buscar_cheque_anulado">Buscar</button>
            <input type="text" class="form-control numerocheque_anulado mt-1" readonly value="">
        </td>
        <td>
            <select name="origen_reemplazo[]" class="form-control origen_reemplazo">
                <option value="E">Emitido</option>
                <option value="R">Recibido</option>
            </select>
        </td>
        <td><input type="text" name="numerocheque_reemplazo[]" class="form-control numerocheque_reemplazo" value=""></td>
        <td><input type="date" name="fechapago_reemplazo[]" class="form-control fechapago_reemplazo" value=""></td>
        <td>
            <input type="hidden" name="cuentacaja_reemplazo_ids[]" class="cuentacaja_reemplazo_id" value="">
            <input type="hidden" name="banco_reemplazo_ids[]" class="banco_reemplazo_id" value="">
            <input type="hidden" name="chequera_reemplazo_ids[]" class="chequera_reemplazo_id" value="">
            <input type="hidden" name="cotizacioncheque_reemplazo[]" class="cotizacioncheque_reemplazo" value="0">
            <div class="bloque-reemplazo-emitido">
                <button type="button" class="btn-accion-tabla consultacuentacaja_reemplazo tooltipsC" title="Cuenta banco">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigo_reemplazo form-control d-inline-block" style="width:80px" readonly value="">
            </div>
            <div class="bloque-reemplazo-recibido" style="display:none">
                <button type="button" class="btn-accion-tabla consultabanco_reemplazo tooltipsC" title="Banco">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="nombrebanco_reemplazo form-control d-inline-block" style="width:120px" readonly value="">
            </div>
        </td>
        <td><input type="number" name="montocheque_reemplazo[]" class="form-control montocheque_reemplazo" value=""></td>
        <td>
            <select name="moneda_reemplazo_ids[]" class="form-control moneda_reemplazo_id">
                @foreach ($moneda_query as $m)
                    <option value="{{ $m->id }}">{{ $m->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <button type="button" class="btn-accion-tabla eliminar_cheque_reemplazo tooltipsC" title="Eliminar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
