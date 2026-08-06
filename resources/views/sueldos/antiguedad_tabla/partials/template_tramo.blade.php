<template id="template-antiguedad-tramo">
    <tr class="item-antiguedad-tramo">
        <td>
            <input type="number" name="tramos[__IDX__][nro_linea]" class="form-control form-control-sm nro-linea" min="1" value=""/>
        </td>
        <td>
            <input type="number" name="tramos[__IDX__][anio]" class="form-control form-control-sm" min="1" max="80" value="" placeholder="Años"/>
        </td>
        <td>
            <input type="number" name="tramos[__IDX__][porcentaje]" class="form-control form-control-sm" step="0.000001" value="" placeholder="0"/>
        </td>
        <td>
            <input type="number" name="tramos[__IDX__][cantidad]" class="form-control form-control-sm" step="0.01" value="" placeholder="0"/>
        </td>
        <td class="text-center align-middle">
            <a href="#" class="btn-accion-tabla eliminar_antiguedad_tramo tooltipsC" title="Quitar tramo">
                <i class="fa fa-times-circle text-danger"></i>
            </a>
        </td>
    </tr>
</template>
