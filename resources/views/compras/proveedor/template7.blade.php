<template id="template-renglon-servicio">
    <tr class="item-servicio">
        <td>
            <input type="text" name="servicios_nros[]" class="form-control iiservicio" readonly value="1" />
            <input type="hidden" name="servicios_empresa_ids[]" class="servicio-empresa-id" value="" />
        </td>
        <td>
            <input type="text" name="servicios_clientes[]" class="form-control servicio-cliente"
                value="" maxlength="255" placeholder="Nro. cliente / medidor" />
        </td>
        <td>
            <input type="text" name="servicios_detalles[]" class="form-control servicio-detalle"
                value="" maxlength="255" placeholder="Detalle" />
        </td>
        <td>
            <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_servicio tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
