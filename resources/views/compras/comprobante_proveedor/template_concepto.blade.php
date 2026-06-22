<template id="template-renglon-concepto">
    <tr class="item-concepto">
        <td>
            <select name="concepto_ivacompra_ids[]" class="form-control concepto_ivacompra_id">
                <option value="">-- Elija concepto de iva compra --</option>
                @foreach ($concepto_ivacompra_query as $concepto)
                    @include('compras.comprobante_proveedor.partials.option_concepto_ivacompra', [
                        'concepto' => $concepto,
                    ])
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="montos[]" class="form-control monto" value="" />
        </td>
        <td class="text-center align-middle cp-celda-aviso-concepto">
            <span class="cp-aviso-concepto-cuenta text-muted" title=""></span>
        </td>
        <td>
            <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_concepto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>
