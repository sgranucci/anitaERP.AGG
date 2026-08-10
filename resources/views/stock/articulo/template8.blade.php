<template id="template-renglon-articulo-proveedor">
    <tr class="item-articulo-proveedor">
        <td class="col-proveedor">
            <input type="hidden" class="ap_linea_id" name="ap_linea_ids[]" value="">
            <input type="hidden" class="proveedor_id" name="ap_proveedor_ids[]" value="">
            <div class="d-flex align-items-center flex-nowrap">
                <input type="text" class="form-control form-control-sm codigoproveedor mr-1" style="width: 4.5rem; flex-shrink: 0;" value="">
                <button type="button" title="Consulta proveedores" class="btn-accion-tabla consultaproveedor tooltipsC mr-1">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" class="form-control form-control-sm nombreproveedor" value="" readonly>
            </div>
        </td>
        <td>
            <input type="text" name="ap_nombres_articulo_proveedor[]" class="form-control form-control-sm" maxlength="255" value="">
        </td>
        <td class="col-codbarra">
            <input type="text" name="ap_codigosbarra[]" class="form-control form-control-sm ap-codigobarra" maxlength="13" inputmode="numeric" pattern="[0-9]*" value="">
        </td>
        <td>
            <input type="text" name="ap_codigos_articulo_proveedor[]" class="form-control form-control-sm" maxlength="100" value="">
        </td>
        <td class="col-moneda">
            <input type="text" class="form-control form-control-sm ap-moneda-vigente" value="—" readonly tabindex="-1">
        </td>
        <td class="col-precio">
            <input type="text" class="form-control form-control-sm ap-precio-vigente text-muted" readonly tabindex="-1" value="—" title="Sin precio en lista activa">
        </td>
        <td class="col-vigencia">
            <input type="text" class="form-control form-control-sm ap-vigencia-lista" readonly tabindex="-1" value="—">
        </td>
        <td>
            <select name="ap_unidadmedida_compra_ids[]" class="form-control form-control-sm ap-um-compra">
                <option value="">—</option>
                @foreach ($unidadmedida as $um)
                    <option value="{{ $um->id }}" @if(isset($producto) && (int) $um->id === (int) ($producto->unidadmedida_id ?? 0)) selected @endif>{{ $um->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.000001" min="0.000001" name="ap_coeficientes_conversion[]" class="form-control form-control-sm ap-coef-conversion" value="1" title="Solo si UM compra ≠ UM artículo">
        </td>
        <td class="col-activo text-center align-middle">
            <input type="hidden" name="ap_activos[]" class="ap-activo-val" value="1">
            <input type="checkbox" class="ap-activo-check" value="1" checked>
        </td>
        <td class="col-preferido text-center align-middle">
            <input type="radio" name="ap_preferido_proveedor_id" class="ap-preferido" value="">
        </td>
        <td class="col-lista text-center align-middle px-1 ap-celda-lista">
            <span class="badge badge-secondary px-1 ap-badge-lista tooltipsC" title="Sin lista de precios activa con este art&iacute;culo"><i class="fa fa-minus"></i></span>
        </td>
        <td class="col-accion text-center align-middle px-1">
            <button type="button" title="Eliminar l&iacute;nea" class="btn-accion-tabla eliminar_articulo_proveedor tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
