<template id="template-renglon-ordenventa-cuota">
    <tr class="item-ordenventa-cuota">
        <td>
            <input type="number" name="cuotas[]" class="form-control iicuota" readonly value="1" />
        </td>							
        <td>
            <input type="date" name="fechafacturas[]" class="form-control fechafactura" value="">
        </td>
        <td>
            <input type="number" name="montofacturas[]" class="form-control montofactura" value="">
        </td>
        <td>
            <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_ordenventa_cuota tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>