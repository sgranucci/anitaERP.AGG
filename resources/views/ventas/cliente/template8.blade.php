<template id="template-renglon-cm05">
    <tr class="item-cm05">
        <td>
            <input type="hidden" name="cliente_cm05[]" class="form-control iicm05" readonly value="1" />
            <div class="form-group row" id="provincia">
                <input type="hidden" class="provincia_id" name="provincia_ids[]" value="" >
                <input type="hidden" class="provincia_id_previa" name="provincia_id_previa[]" value="" >
                <button type="button" title="Consulta provincias" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 80px;HEIGHT: 38px" class="codigoprovincia form-control" name="codigoprovincias[]" value="" >
                <input type="hidden" class="codigo_previo_provincia" name="codigo_previo_provincias[]" value="" >
            </div>
        </td>							
        <td>
            <input type="text" style="WIDTH: 250px; HEIGHT: 38px" class="nombreprovincia form-control" name="nombreprovincias[]" value="" readonly>
        </td>
        <td>
            <select name="tipopercepciones[]" class="form-control tipopercepcion requerido" required>
                <option value="">-- Elija tipo de percepción --</option>
                @foreach ($tipopercepcion_enum as $value => $tipo)
                    <option value="{{ $value }}">{{ $tipo }}</option>
                @endforeach
            </select>
        </td>							
        <td>
            <input type="number" min="0" max="100" class="coeficiente form-control" name="coeficientes[]" value="">
        </td>
        <td>
            <input type="date" class="fechavigencia form-control" name="fechavigencias[]" value="">
        </td>
        <td>
            <select name="certificadonoretenciones[]" class="form-control certificadonoretencion requerido" required>
                @foreach ($certificadonoretencion_enum as $value => $certificado)
                    <option value="{{ $value }}">{{ $certificado }}</option>
                @endforeach
            </select>
        </td>									
        <td>
            <input type="date" class="desdefechanoretencion form-control" name="desdefechanoretenciones[]" value="">
        </td>							
        <td>
            <input type="date" class="hastafechanoretencion form-control" name="hastafechanoretenciones[]" value="">
        </td>							
        <input type="hidden" name="creousuario_cm05_ids[]" class="form-control creousuario_cm05_id" value="{{ auth()->id() }}"/>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_cm05 tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>