<template id="template-renglon-cheque-recibido">
    <tr class="item-cheque-recibido">
        <td><input type="date" name="fechapago_recibidos[]" class="form-control fechapago_recibido" value=""></td>
        <td>
            <input type="hidden" name="cheque_recibido_ids[]" class="cheque_recibido_id" value="">
            <input type="hidden" name="banco_recibido_ids[]" class="banco_recibido_id" value="">
            <button type="button" class="btn-accion-tabla consultabanco_recibido tooltipsC" title="Consulta banco">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" class="codigobanco_recibido form-control d-inline-block" style="width:70px" name="codigobanco_recibido[]" value="">
            <input type="text" class="nombrebanco_recibido form-control d-inline-block" style="width:140px" readonly value="">
        </td>
        <td><input type="text" name="numerocheque_recibidos[]" class="form-control numerocheque_recibido" value=""></td>
        <td><input type="text" name="sucursalpago_recibidos[]" class="form-control sucursalpago_recibido" value=""></td>
        <td><input type="text" name="cuentalibradora_recibidos[]" class="form-control cuentalibradora_recibido" value=""></td>
        <td>
            <select name="monedacheque_recibido_ids[]" class="form-control monedacheque_recibido_id">
                @foreach ($moneda_query as $m)
                    <option value="{{ $m->id }}">{{ $m->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="montocheque_recibidos[]" class="form-control montocheque_recibido" min="0" step="0.01" value=""></td>
        <td><input type="number" name="cotizacioncheque_recibidos[]" class="form-control cotizacioncheque_recibido" step="0.0001" value="0"></td>
        <td>
            <button type="button" class="btn-accion-tabla eliminar_cheque_recibido tooltipsC" title="Eliminar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
