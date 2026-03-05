<template id="template-renglon-concepto">
    <tr class="item-concepto">
        <td>
            <select name="concepto_ivacompra_ids[]" data-placeholder="Concepto de iva compra" class="form-control concepto_ivacompra_id" data-fouc>
                <option value="">-- Elija concepto de iva compra --</option>
                @foreach ($concepto_ivacompra_query as $concepto)
                    <option value="{{ $concepto->id }}">{{ $concepto->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" name="montos[]" class="form-control monto" value="" />
        </td>                    
        <td>
            <button style="width: 7%;" type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_concepto tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        </td>
    </tr>
</template>