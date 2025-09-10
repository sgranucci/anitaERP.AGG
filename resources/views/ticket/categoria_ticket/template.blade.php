<template id="template-renglon-subcategoria-ticket">
    <tr class="item-subcategoria-ticket">
        <td>
            <input type="text" name="subcategoria[]" class="form-control iisubcategoria" readonly value="1" />
        </td>
        <td>
            <input type="text" class="nombre_subcategoria form-control" name="nombre_subcategorias[]" value="">
        </td>
        <td>
            <button type="button" style="width: 7%;" title="Elimina esta linea" class="btn-accion-tabla eliminar_subcategoria tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>