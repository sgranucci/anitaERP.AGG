<template id="template-vianda-articulo-dia">
    <div class="vianda-articulo-fila mb-2 item-vianda-articulo-dia tm-articulo-campo" data-dia="__DIA__">
        <input type="hidden" class="articulo_id" name="articulo_por_dia[__DIA__][]" value="">
        <div class="input-group input-group-sm mb-1">
            <div class="input-group-prepend">
                <button type="button" title="Consulta art&iacute;culos (F1)" class="btn btn-outline-primary btn-sm consultaarticulo">
                    <i class="fa fa-search"></i>
                </button>
            </div>
            <input type="text" class="form-control form-control-sm codigoarticulo" name="codigoarticulos_dia[__DIA__][]" value=""
                   placeholder="SKU" title="Enter valida el SKU; F1 abre la consulta">
        </div>
        <input type="text" class="form-control form-control-sm descripcionarticulo mb-1" name="descripcionarticulos_dia[__DIA__][]" value="" readonly placeholder="Descripci&oacute;n">
        <button type="button" title="Quitar art&iacute;culo" class="btn btn-sm btn-link text-danger p-0 eliminar-articulo-dia">
            <i class="fa fa-times-circle"></i> Quitar
        </button>
    </div>
</template>
