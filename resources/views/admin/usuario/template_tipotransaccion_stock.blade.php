<template id="template-renglon-usuario-tipotransaccion-stock">
    <tr class="item-usuario-tipotransaccion-stock tm-tipotransaccion-stock-campo">
        <td>
            <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                <input type="hidden" name="tipotransaccion_stock_ids[]" class="tipotransaccion_stock_id" value="">
                <button type="button" title="Consulta tipos de transacci&oacute;n" class="btn-accion-tabla consultatipotransaccionstock tooltipsC">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm abreviaturatipotransaccionstock" value="" autocomplete="off" style="max-width: 6rem;">
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm nombretipotransaccionstock" value="" readonly>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm operacion-tipotransaccion-stock" value="" readonly>
        </td>
        <td>
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_usuario_tipotransaccion_stock tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
