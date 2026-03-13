<template id="template-renglon-partidagasto-monto">
    <tr>
        <td>
            <input type="hidden" name="items_monto[]" class="item" value="" />
            <input type="hidden" name="partidagasto_monto_ids[]" class="form-control partidagasto_monto_id" readonly value="" />
            <input type="hidden" name="creousuario_ids_monto[]" class="creousuario_id_monto" value="{{ auth()->id() }}" />
            <input type="month" name="periodos[]" min="2010/01" placeholder="Formato: AAAA-MM" class="form-control periodo" value="">
        </td>
        <td>
            <input type="text" name="montos[]" class="form-control monto" value="">
        </td>
        <td>
            <button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_partidagasto_monto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>