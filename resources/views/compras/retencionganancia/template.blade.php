<template id="template-renglon">
        <tr class="item-cuota">
            <td>
                <input type="number" id="icuota" name="cuotas[]" class="form-control iicuota" readonly value="1" />
            </td>
            <td>
                <input type="number" id="desdemonto" name="desdemontos[]" class="form-control desdemonto" value="0"/>
            </td>
            <td>
                <input type="number" id="hastamonto" name="hastamontos[]" class="form-control hastamonto" value="0" />
            </td>
            <td>
                <input type="number" id="montoretencion" name="montoretenciones[]" class="form-control montoretencion" value="0" />
            </td>
            <td>
                <input type="number" id="porcentajeretencion" name="porcentajeretenciones[]" class="form-control porcentajeretencion" value="0" />
            </td>						
            <td>
                <input type="number" id="excedente" name="excedentes[]" class="form-control excedente" value="0" />
            </td>			
            <td>
                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar tooltipsC">
                    <i class="fa fa-times-circle text-danger"></i>
                </button>
            </td>
        </tr>
</template>