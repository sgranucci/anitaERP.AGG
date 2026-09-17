<template id="template-renglon-archivo">
    <tr class="item-archivo">
        <td>
            <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos" onchange="actualizaArchivo(this)">
            <input type="hidden" name="nombresanteriores[]" class="form-control nombresanteriores" value="">
        </td>
        <td></td>
        <td class="text-nowrap">
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminararchivo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
