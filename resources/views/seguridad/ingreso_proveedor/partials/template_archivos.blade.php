<template id="ingreso-template-renglon-archivo">
    <tr class="item-archivo-ingreso">
        <td>
            <input type="hidden" name="archivo_tipo[]" value="">
            <input type="file" name="nombrearchivos[]" class="form-control ingreso-nombrearchivos">
            <input type="hidden" name="archivo_vencimiento[]" value="">
        </td>
        <td class="text-center align-middle">
            <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla ingreso-eliminararchivo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
