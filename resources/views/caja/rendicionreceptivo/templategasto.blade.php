<template id="template-renglon-rendicionreceptivo-gasto">
    <tr class="item-rendicionreceptivo-gasto">
        <td>
            <div class="form-group row" id="conceptogasto">
                <input type="hidden" name="gasto_ids[]" class="form-control gasto_id" value="" />
                <input type="hidden" name="conceptogasto[]" class="form-control iiconceptogasto" value="" />
                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="conceptogasto_id" name="conceptogasto_ids[]" value="" >
                <input type="hidden" class="conceptogasto_id_previa" name="conceptogasto_id_previa[]" value="" >
                <button type="button" title="Consulta conceptos" style="padding:1;" class="btn-accion-tabla consultaconceptogasto tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 100px;HEIGHT: 38px" class="nombreconceptogasto form-control" name="nombreconceptogastos[]" value="" >
            </div>
        </td>	
        <td>
            <div class="form-group row" id="cuenta">
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
            <input type="number" style="WIDTH: 150px; HEIGHT: 38px" name="montos[]" class="form-control monto" value="">
        </td>
        <td>
            <input type="number" style="WIDTH: 120px; HEIGHT: 38px" name="cotizaciones[]" class="form-control cotizacion" value="0">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_rendicionreceptivo_gasto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>