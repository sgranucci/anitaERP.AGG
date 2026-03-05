<template id="template-renglon-voucher-formapago">
    <tr class="item-cuenta">
        <td>
            <div class="form-group row" id="cuenta">
                <input type="hidden" name="cuentacaja[]" class="form-control iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacaja_id" name="cuentacaja_ids[]" value="" >
                <input type="hidden" class="cuentacaja_id_previa" name="cuentacaja_id_previa[]" value="" >
                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuentacaja tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="codigo form-control" name="codigos[]" value="" >
                <input type="hidden" class="codigo_previo" name="codigo_previos[]" value="" >
            </div>
        </td>							
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombre form-control" name="nombres[]" value="" readonly >
        </td>
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control" required readonly data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="montos[]" class="form-control monto" value="">
        </td>
        <td>
            <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="0">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_voucher_formapago tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>