<template id="template-renglon-cuenta">
    <tr class="item-cuenta">
        <td>
            <div class="form-group row" id="cuenta">
                <input type="hidden" name="cuenta[]" class="form-control iicuenta" readonly value="1" />
                <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="" >
                <input type="hidden" class="cuentacontable_id_previa" name="cuentacontable_id_previa[]" value="" >
                <button type="button" title="Consulta cuentas" style="padding:1;" class="btn-accion-tabla consultacuenta tooltipsC">
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
            <select name="centrocosto_ids[]" data-placeholder="Centro de costo" class="centrocosto form-control" data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($centrocosto_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->nombre }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <select name="moneda_ids[]" data-placeholder="Moneda" class="moneda form-control" required data-fouc>
                <option value="">-- Seleccionar --</option>
                @foreach($moneda_query as $key => $value)
                    <option value="{{ $value->id }}">{{ $value->abreviatura }}</option>    
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="debes[]" class="form-control debe" value="">
        </td>
        <td>
            <input type="number" name="haberes[]" class="form-control haber" value="">
        </td>
        <td>
            <input type="number" name="cotizaciones[]" class="form-control cotizacion" value="0">
        </td>
        <td>
            <input type="text" name="observaciones[]" class="form-control observacion" value="">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cuenta tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>