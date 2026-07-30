<template id="template-cuenta-suss">
    <tr class="item-cuentacontable">
        <td>
            @include('includes.form-empresa-asignada-control', [
                'empresa_query' => $empresa_query,
                'empresa_id' => null,
                'name' => 'empresa_ids[]',
                'select_class' => 'empresa',
                'permite_vacio' => true,
                'opcion_vacia' => '-- Seleccionar --',
            ])
        </td>
        <td>
            <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="">
            <button type="button" class="btn-accion-tabla consultacuentacontable tooltipsC">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" style="width:120px;display:inline-block" class="codigocuentacontable form-control d-inline-block">
            <input type="text" style="width:280px;display:inline-block" class="nombrecuentacontable form-control d-inline-block" readonly>
        </td>
        <td>
            <button type="button" class="btn-accion-tabla eliminar_cuentacontable tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
