<template id="template-renglon-sp-cuota">
    <tr class="item-sp-cuota">
        <td>
            <input type="number" min="1" name="nro_cuotas[]" class="form-control nro-cuota" value="1">
        </td>
        <td>
            <input type="date" name="fecha_vencimientos_cuota[]" class="form-control" value="">
        </td>
        <td>
            <input type="text" inputmode="decimal" name="montos_cuota[]" class="form-control text-right js-monto-ar"
                   value="0,00" autocomplete="off" placeholder="0,00">
        </td>
        <td>
            <input type="hidden" name="solicitudpago_hija_ids[]" value="">
            <span class="text-muted">Pendiente</span>
        </td>
        <td class="text-center">
            <button type="button" class="btn-accion-tabla eliminar_sp_cuota tooltipsC" title="Eliminar cuota">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
