<template id="template-renglon-listaprecio-articulo">
    <tr class="item-listaprecio-articulo">
        <td>
            <input type="hidden" class="linea_id" name="linea_ids[]" value="">
            <div class="form-group celda-articulo-listaprecio mb-0 d-flex align-items-center flex-nowrap">
                <input type="hidden" class="articulo_id" name="articulo_ids[]" value="">
                <button type="button" title="Consulta art&iacute;culos (F1)" style="padding:1;" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="codigoarticulo codigoarticulolocal form-control flex-shrink-0" style="width: 140px; max-width: 15vw; height: 38px;" name="codigoarticulos[]" value="" placeholder="SKU" title="F1 consulta; Enter resuelve el c&oacute;digo">
            </div>
        </td>
        <td>
            <input type="text" class="descripcionarticulo form-control form-control-sm" name="descripcionarticulos[]" value="" readonly>
        </td>
        <td>
            <input type="number" step="0.000001" name="precios[]" class="form-control form-control-sm text-right" value="">
        </td>
        <td>
            <input type="number" step="0.01" name="descuentos[]" class="form-control form-control-sm text-right" value="0">
        </td>
        <td>
            <input type="text" name="codigos_articulo_proveedor[]" class="form-control form-control-sm" maxlength="100" value="">
        </td>
        <td>
            <input type="date" name="fechavigencias[]" class="form-control form-control-sm" value="" required>
        </td>
        <td class="text-center">
            <button type="button" title="Eliminar l&iacute;nea" class="btn-accion-tabla eliminar_listaprecio_articulo tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
