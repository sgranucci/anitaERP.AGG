<template id="template-renglon-cp-articulo">
    <tr class="item-cp-articulo">
        <td>
            <input type="hidden" name="articulo_ids[]" class="articulo_id" value="">
            <div class="d-flex flex-wrap align-items-center">
                <input type="text" name="articulo_skus[]" class="form-control form-control-sm codigoarticulo mr-1"
                    value="" style="width:6.5rem;" autocomplete="off"
                    title="SKU + Enter · F1 consulta" placeholder="SKU">
                <button type="button" class="btn btn-outline-primary btn-sm consultaarticulo tooltipsC flex-shrink-0"
                    title="Consulta artículos (F1)">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </td>
        <td>
            <input type="text" name="articulo_codigos_proveedor[]" class="form-control form-control-sm"
                value="" maxlength="80" autocomplete="off">
        </td>
        <td>
            <input type="text" name="articulo_descripciones[]" class="form-control form-control-sm descripcionarticulo"
                value="" maxlength="255">
        </td>
        <td>
            <input type="text" inputmode="decimal" name="articulo_cantidades[]"
                class="form-control form-control-sm js-monto-ar text-right" value="">
        </td>
        <td>
            <input type="text" inputmode="decimal" name="articulo_precios[]"
                class="form-control form-control-sm js-monto-ar text-right" value="">
        </td>
        <td class="text-center align-middle">
            <button type="button" class="btn-accion-tabla eliminar_cp_articulo tooltipsC" title="Eliminar línea">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
