<template id="template-renglon-guia">
    <tr class="item-guia">
        <td>
            <div class="form-group row" id="guia">
                <input type="hidden" name="guia[]" class="form-control iiguia" readonly value="" />
                <input type="hidden" class="guia_id" name="guia_ids[]" value="" >
                <input type="text" style="WIDTH: 40px;HEIGHT: 38px" class="codigoguia" name="codigoguias[]" value="" >
                <input type="hidden" class="guia_id_previa" name="guia_id_previa[]" value="" >
                <button type="button" title="Consulta guías" style="padding:1;" class="btn-accion-tabla consultaguia tooltipsC">
                        <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" style="WIDTH: 250px;HEIGHT: 38px" class="nombreguia form-control" name="nombreguias[]" value="" >
            </div>
        </td>
        <td>
            <select name="tipocomisiones[]" class="col-lg-8 form-control tipocomision" required>
                <option value="">-- Elija tipo de comisión --</option>
                @foreach ($tipocomision_enum as $tipocomision)
                    <option value="{{ $tipocomision['valor'] }}">{{ $tipocomision['nombre'] }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="porcentajecomisiones[]" class="form-control porcentajecomision" value="">
        </td>
        <td>
            <input type="number" name="montocomisiones[]" class="form-control montocomision" value="">
        </td>
        <td>
            <input type="number" name="ordenservicio_ids[]" class="form-control ordenservicio_id" value="">
        </td>
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_guia tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>