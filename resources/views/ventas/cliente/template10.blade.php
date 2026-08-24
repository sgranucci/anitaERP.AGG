<template id="template-renglon-exclusion-percepcion">
    <tr class="item-exclusion-percepcion">
        <td>
            <input type="hidden" name="exclusion_ids[]" value="" />
            <input type="hidden" name="exclusion_creousuario_ids[]" class="form-control exclusion-creousuario-id" value="{{ auth()->id() }}"/>
            <select name="exclusion_tipos[]" class="form-control tipoexclusion">
                <option value="">-- Tipo --</option>
                @foreach ($tipoexclusion_enum ?? \App\Models\Ventas\Cliente_Exclusion_Percepcion::$enumTipo as $value => $tipo)
                    <option value="{{ $value }}">{{ $tipo }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <div class="form-group row mb-0 exclusion-provincia-grupo">
                <input type="hidden" class="provincia_id" name="exclusion_provincia_ids[]" value="" >
                <input type="hidden" class="provincia_id_previa" name="exclusion_provincia_id_previa[]" value="" >
                <button type="button" title="Consulta provincias (F1)" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 60px;HEIGHT: 38px" class="codigoprovincia form-control" name="exclusion_codigoprovincias[]" value="" >
                <input type="hidden" class="codigo_previo_provincia" name="exclusion_codigo_previo_provincias[]" value="" >
            </div>
        </td>
        <td>
            <input type="text" style="WIDTH: 180px; HEIGHT: 38px" class="nombreprovincia form-control" name="exclusion_nombreprovincias[]" value="" readonly>
        </td>
        <td>
            <input type="text" inputmode="decimal" class="porcentajeexclusion form-control" name="exclusion_porcentajes[]" value="0.0000" autocomplete="off">
        </td>
        <td>
            <input type="date" class="desdefechaexclusion form-control" name="exclusion_desdefechas[]" value="">
        </td>
        <td>
            <input type="date" class="hastafechaexclusion form-control" name="exclusion_hastafechas[]" value="">
        </td>
        <td>
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_exclusion_percepcion tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
