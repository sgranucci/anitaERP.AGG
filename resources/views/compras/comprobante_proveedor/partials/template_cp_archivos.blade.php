<template id="cp-template-renglon-archivo">
    <tr class="item-archivo-cp">
        <td>
            <input type="file" name="nombrearchivos[]" class="form-control cp-nombrearchivos" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp">
        </td>
        <td>
            <select name="archivo_tipos[]" class="form-control form-control-sm cp-archivo-tipo">
                <option value="FACTURA">Factura (PDF)</option>
                <option value="REMITO">Remito</option>
                <option value="ADJUNTO">Adjunto</option>
                <option value="CONTABLE">Respaldo contable</option>
            </select>
        </td>
        <td>
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla cp-eliminararchivo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
