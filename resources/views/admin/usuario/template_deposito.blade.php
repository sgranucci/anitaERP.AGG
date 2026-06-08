<template id="template-renglon-usuario-deposito">
    <tr class="item-usuario-deposito">
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" name="deposito_ids[]" class="deposito_id" value="">
                <button type="button" title="Consulta depósitos" class="btn-accion-tabla consultadeposito tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm codigodeposito" value="" autocomplete="off" style="max-width: 6rem;">
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm descripciondeposito" value="" readonly>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm empresa-deposito-nombre" value="" readonly>
        </td>
        <td>
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_usuario_deposito tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
